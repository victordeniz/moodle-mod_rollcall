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

namespace mod_rollcall\external;

use core_external\external_api;
use mod_rollcall\local\category_manager;
use mod_rollcall\local\classification;
use mod_rollcall\persistent\category;

/**
 * Tests for the set_category external function.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(set_category::class)]
final class set_category_test extends \core_external\tests\externallib_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass Activity record with cmid. */
    private \stdClass $roll;
    /** @var category */
    private category $erasmus;
    /** @var \stdClass */
    private \stdClass $editor;
    /** @var \stdClass */
    private \stdClass $viewer;
    /** @var \stdClass */
    private \stdClass $s1;
    /** @var \stdClass */
    private \stdClass $s2;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->roll = $gen->create_module('rollcall', ['course' => $this->course->id, 'groupmode' => SEPARATEGROUPS]);
        $this->editor = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->viewer = $gen->create_and_enrol($this->course, 'teacher');
        $this->s1 = $gen->create_and_enrol($this->course, 'student');
        $this->s2 = $gen->create_and_enrol($this->course, 'student');
        $this->erasmus = $gen->get_plugin_generator('mod_rollcall')->create_category(['name' => 'E', 'color' => 'blue']);
    }

    /**
     * Calls the external function the way the web service layer does.
     *
     * @param int $categoryid
     * @param int[] $userids
     * @return array
     */
    private function call(int $categoryid, array $userids): array {
        $result = set_category::execute($this->roll->cmid, $categoryid, $userids);
        return external_api::clean_returnvalue(set_category::execute_returns(), $result);
    }

    /**
     * Current effective category id of a user (0 for regular).
     *
     * @param int $userid
     * @return int
     */
    private function category_of(int $userid): int {
        $effective = classification::get_effective($this->course->id, [$userid])[$userid];
        return $effective ? (int)$effective->get('id') : 0;
    }

    /**
     * Puts the editing teacher in separate groups without access to all groups: g1 = {editor, s1}, g2 = {s2}.
     */
    private function restrict_editor_to_group(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $g1 = $gen->create_group(['courseid' => $this->course->id]);
        $g2 = $gen->create_group(['courseid' => $this->course->id]);
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $this->editor->id]);
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $this->s1->id]);
        $gen->create_group_member(['groupid' => $g2->id, 'userid' => $this->s2->id]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, \context_course::instance($this->course->id));
    }

    public function test_editing_teacher_can_classify_and_return_value(): void {
        $this->setUser($this->editor);
        $eid = (int)$this->erasmus->get('id');

        $result = $this->call($eid, [$this->s1->id]);

        $this->assertSame([['userid' => (int)$this->s1->id, 'categoryid' => $eid]], $result);
        $this->assertSame($eid, $this->category_of($this->s1->id));
    }

    public function test_regular_with_zero(): void {
        $this->setUser($this->editor);
        $this->call($this->erasmus->get('id'), [$this->s1->id, $this->s2->id]);

        $result = $this->call(0, [$this->s1->id]);

        $this->assertSame([['userid' => (int)$this->s1->id, 'categoryid' => 0]], $result);
        $this->assertSame(0, $this->category_of($this->s1->id));
        $this->assertSame((int)$this->erasmus->get('id'), $this->category_of($this->s2->id));
    }

    public function test_non_editing_teacher_cannot_classify(): void {
        $this->setUser($this->viewer);
        $this->expectException(\required_capability_exception::class);
        $this->call($this->erasmus->get('id'), [$this->s1->id]);
    }

    public function test_rejects_non_listed_user(): void {
        $this->setUser($this->editor);
        try {
            $this->call($this->erasmus->get('id'), [$this->s1->id, $this->viewer->id]);
            $this->fail('A user who is not listed must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('usernotlisted', $e->errorcode);
        }
        $this->assertSame(0, $this->category_of($this->s1->id));
    }

    public function test_bulk_validation_does_not_query_per_student(): void {
        global $DB;
        $this->restrict_editor_to_group();
        $g1 = $DB->get_field('groups_members', 'groupid', ['userid' => $this->editor->id]);
        $userids = [];
        for ($i = 0; $i < 60; $i++) {
            $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
            $this->getDataGenerator()->create_group_member(['groupid' => $g1, 'userid' => $student->id]);
            $userids[] = (int)$student->id;
        }
        $this->setUser($this->editor);
        $this->call(0, [$this->s1->id]); // Warm up caches unrelated to the batch size.

        $reads = $DB->perf_get_reads();
        $this->call($this->erasmus->get('id'), $userids);
        $reads = $DB->perf_get_reads() - $reads;

        // Validation must not grow with the number of students (the per-student checks needed well over 120 reads).
        $this->assertLessThan(60, $reads);
    }

    public function test_rejects_enrolled_site_admin(): void {
        $admin = get_admin();
        $this->getDataGenerator()->enrol_user($admin->id, $this->course->id, 'editingteacher');
        $this->setUser($this->editor);

        try {
            $this->call($this->erasmus->get('id'), [$admin->id]);
            $this->fail('A site admin is never listed, so they must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('usernotlisted', $e->errorcode);
        }
    }

    public function test_rejects_user_outside_teacher_groups(): void {
        $this->restrict_editor_to_group();
        $this->setUser($this->editor);

        try {
            $this->call($this->erasmus->get('id'), [$this->s1->id, $this->s2->id]);
            $this->fail('A student outside the teacher groups must be rejected.');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->assertSame(0, $this->category_of($this->s1->id));
        $this->assertSame(0, $this->category_of($this->s2->id));

        // A student in the teacher's own group is accepted.
        $this->call($this->erasmus->get('id'), [$this->s1->id]);
        $this->assertSame((int)$this->erasmus->get('id'), $this->category_of($this->s1->id));
    }

    public function test_accessallgroups_allows_any_group(): void {
        $this->restrict_editor_to_group();
        $roleid = \core\di::get(\moodle_database::class)->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $roleid, \context_course::instance($this->course->id), true);
        $this->setUser($this->editor);

        $this->call($this->erasmus->get('id'), [$this->s1->id, $this->s2->id]);

        $this->assertSame((int)$this->erasmus->get('id'), $this->category_of($this->s2->id));
    }

    public function test_rejects_hidden_category(): void {
        category_manager::set_visible($this->erasmus->get('id'), false);
        $this->setUser($this->editor);

        try {
            $this->call($this->erasmus->get('id'), [$this->s1->id]);
            $this->fail('A hidden category must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidcategory', $e->errorcode);
        }
    }
}
