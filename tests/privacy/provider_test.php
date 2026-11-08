<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_rollcall\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_rollcall\local\attendance_manager;
use mod_rollcall\local\classification;
use mod_rollcall\local\session_manager;
use mod_rollcall\local\status_manager;
use mod_rollcall\persistent\category;

/**
 * Tests for the privacy provider.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \context_course */
    private \context_course $coursecontext;
    /** @var \stdClass Teacher who classifies. */
    private \stdClass $teacher;
    /** @var \stdClass */
    private \stdClass $s1;
    /** @var \stdClass */
    private \stdClass $s2;
    /** @var category */
    private category $erasmus;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->coursecontext = \context_course::instance($this->course->id);
        $roll = $gen->create_module('rollcall', ['course' => $this->course->id]);
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->s1 = $gen->create_and_enrol($this->course, 'student');
        $this->s2 = $gen->create_and_enrol($this->course, 'student');
        $this->setAdminUser();
        $this->erasmus = $gen->get_plugin_generator('mod_rollcall')->create_category(['name' => 'Erasmus X', 'color' => 'blue']);

        $this->setUser($this->teacher);
        classification::set($this->course->id, [$this->s1->id, $this->s2->id], $this->erasmus->get('id'),
            \context_module::instance($roll->cmid));
    }

    /**
     * Number of classifications in the course.
     *
     * @return int
     */
    private function count_rows(): int {
        global $DB;
        return $DB->count_records('rollcall_usercat', ['courseid' => $this->course->id]);
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_rollcall'));
        $tables = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('rollcall_usercat', $tables);
        $this->assertContains('rollcall_category', $tables);
        $this->assertContains('rollcall_attendance', $tables);
    }

    public function test_get_contexts_for_userid(): void {
        $this->assertEquals([$this->coursecontext->id], provider::get_contexts_for_userid($this->s1->id)->get_contextids());
        $this->assertEquals([$this->coursecontext->id], provider::get_contexts_for_userid($this->teacher->id)->get_contextids());
        $other = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid($other->id)->get_contextids());
    }

    public function test_export_user_data(): void {
        $this->export_context_data_for_user($this->s1->id, $this->coursecontext, 'mod_rollcall');
        $data = writer::with_context($this->coursecontext)->get_data([get_string('privacy:path', 'mod_rollcall')]);

        $this->assertSame('Erasmus X', $data->category);
        $this->assertNotEmpty($data->timemodified);
    }

    public function test_export_for_classifier_hides_students(): void {
        $this->export_context_data_for_user($this->teacher->id, $this->coursecontext, 'mod_rollcall');
        $data = writer::with_context($this->coursecontext)->get_data([get_string('privacy:path', 'mod_rollcall')]);

        $this->assertCount(2, $data->classificationsmade);
        $this->assertStringNotContainsString((string)$this->s1->id, json_encode($data->classificationsmade));
    }

    public function test_delete_data_for_user(): void {
        global $DB;
        $contextlist = new approved_contextlist($this->s1, 'mod_rollcall', [$this->coursecontext->id]);

        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists('rollcall_usercat', ['userid' => $this->s1->id]));
        $this->assertTrue($DB->record_exists('rollcall_usercat', ['userid' => $this->s2->id]));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context($this->coursecontext);
        $this->assertSame(0, $this->count_rows());
    }

    public function test_get_users_in_context_and_delete_data_for_users(): void {
        global $DB;
        $userlist = new userlist($this->coursecontext, 'mod_rollcall');
        provider::get_users_in_context($userlist);
        $ids = $userlist->get_userids();
        sort($ids);
        $expected = [(int)$this->teacher->id, (int)$this->s1->id, (int)$this->s2->id];
        sort($expected);
        $this->assertEquals($expected, array_map('intval', $ids));

        provider::delete_data_for_users(new approved_userlist($this->coursecontext, 'mod_rollcall', [$this->s2->id]));

        $this->assertTrue($DB->record_exists('rollcall_usercat', ['userid' => $this->s1->id]));
        $this->assertFalse($DB->record_exists('rollcall_usercat', ['userid' => $this->s2->id]));
    }

    public function test_delete_for_modifier_anonymises(): void {
        global $DB;
        $contextlist = new approved_contextlist($this->teacher, 'mod_rollcall', [$this->coursecontext->id]);

        provider::delete_data_for_user($contextlist);

        $this->assertSame(2, $this->count_rows());
        $this->assertFalse($DB->record_exists('rollcall_usercat', ['usermodified' => $this->teacher->id]));
    }

    public function test_category_modifier_in_system_context(): void {
        global $DB;
        $admin = get_admin();
        $system = \context_system::instance();
        $this->assertContains((int)$system->id,
            array_map('intval', provider::get_contexts_for_userid($admin->id)->get_contextids()));

        provider::delete_data_for_user(new approved_contextlist($admin, 'mod_rollcall', [$system->id]));

        $this->assertFalse($DB->record_exists('rollcall_category', ['usermodified' => $admin->id]));
        $this->assertTrue($DB->record_exists('rollcall_category', ['id' => $this->erasmus->get('id')]));
    }

    public function test_attendance_privacy(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $this->setAdminUser();
        $rolls = [];
        $contexts = [];
        foreach (['One', 'Two'] as $name) {
            $roll = $gen->create_module('rollcall', ['course' => $this->course->id]);
            $session = session_manager::create((int)$roll->id, 0, "Session $name", 1700000000, 3600);
            $status = array_values(status_manager::get_all((int)$roll->id))[0];
            attendance_manager::set((int)$session->get('id'), (int)$status->get('id'), [(int)$this->s1->id]);
            attendance_manager::set((int)$session->get('id'), (int)$status->get('id'), [(int)$this->s2->id]);
            $rolls[$name] = $roll;
            $contexts[$name] = \context_module::instance($roll->cmid);
        }

        // Contexts.
        $ids = provider::get_contexts_for_userid($this->s1->id)->get_contextids();
        $this->assertContains((int)$contexts['One']->id, array_map('intval', $ids));
        $this->assertContains((int)$contexts['Two']->id, array_map('intval', $ids));

        // Export.
        $this->export_context_data_for_user($this->s1->id, $contexts['One'], 'mod_rollcall');
        $data = writer::with_context($contexts['One'])->get_data([get_string('privacy:pathattendance', 'mod_rollcall')]);
        $this->assertCount(1, $data->attendance);
        $this->assertSame('Session One', $data->attendance[0]->session);
        $this->assertNotEmpty($data->attendance[0]->sessiondate);
        $this->assertNotEmpty($data->attendance[0]->status);
        $this->assertNotEmpty($data->attendance[0]->timemodified);

        // Users in context.
        $userlist = new userlist($contexts['One'], 'mod_rollcall');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->s1->id, $this->s2->id], $userlist->get_userids());

        // Delete one user in one activity.
        provider::delete_data_for_user(new approved_contextlist($this->s1, 'mod_rollcall', [$contexts['One']->id]));
        $this->assertSame(1, $this->count_attendance($rolls['One']->id));
        $this->assertSame(2, $this->count_attendance($rolls['Two']->id));

        // Delete a user list.
        provider::delete_data_for_users(new approved_userlist($contexts['Two'], 'mod_rollcall', [$this->s2->id]));
        $this->assertSame(1, $this->count_attendance($rolls['Two']->id));

        // Delete everything in one activity.
        provider::delete_data_for_all_users_in_context($contexts['One']);
        $this->assertSame(0, $this->count_attendance($rolls['One']->id));
        $this->assertSame(1, $this->count_attendance($rolls['Two']->id));
    }

    /**
     * Number of attendance rows of a roll.
     *
     * @param int $rollcallid
     * @return int
     */
    private function count_attendance(int $rollcallid): int {
        global $DB;
        return $DB->count_records_sql("SELECT COUNT(a.id) FROM {rollcall_attendance} a
                                         JOIN {rollcall_session} s ON s.id = a.sessionid
                                        WHERE s.rollcallid = ?", [$rollcallid]);
    }
}
