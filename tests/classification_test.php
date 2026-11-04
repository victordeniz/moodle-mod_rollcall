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

namespace mod_rollcall;

use mod_rollcall\event\user_category_updated;
use mod_rollcall\local\category_manager;
use mod_rollcall\local\classification;
use mod_rollcall\persistent\category;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/rollcall/lib.php');

/**
 * Tests for the per-course classification of students.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(classification::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(local\hook_callbacks::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('rollcall_reset_userdata')]
final class classification_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \context_module */
    private \context_module $context;
    /** @var category */
    private category $erasmus;
    /** @var category */
    private category $repeater;
    /** @var \stdClass[] Students u1, u2, u3. */
    private array $u = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $this->course->id]);
        $this->context = \context_module::instance($roll->cmid);
        $this->repeater = $gen->get_plugin_generator('mod_rollcall')->create_category(['name' => 'R', 'color' => 'amber']);
        $this->erasmus = $gen->get_plugin_generator('mod_rollcall')->create_category(['name' => 'E', 'color' => 'blue']);
        for ($i = 1; $i <= 3; $i++) {
            $this->u[$i] = $gen->create_and_enrol($this->course, 'student');
        }
    }

    /**
     * Maps an effective-category result to category ids (0 for regular).
     *
     * @param array $effective userid => category|null
     * @return array userid => int
     */
    private function ids(array $effective): array {
        return array_map(fn(?category $c) => $c ? (int)$c->get('id') : 0, $effective);
    }

    public function test_set_and_get_effective(): void {
        $u = $this->u;
        $result = classification::set($this->course->id, [$u[1]->id, $u[2]->id], $this->erasmus->get('id'), $this->context);

        $eid = (int)$this->erasmus->get('id');
        $this->assertSame([$u[1]->id => $eid, $u[2]->id => $eid], $result);
        $this->assertSame(
            [$u[1]->id => $eid, $u[2]->id => $eid, $u[3]->id => 0],
            $this->ids(classification::get_effective($this->course->id, [$u[1]->id, $u[2]->id, $u[3]->id]))
        );
    }

    public function test_get_effective_empty(): void {
        $this->assertSame([], classification::get_effective($this->course->id, []));
    }

    public function test_change_category_updates_row(): void {
        global $DB;
        $uid = $this->u[1]->id;
        classification::set($this->course->id, [$uid], $this->erasmus->get('id'), $this->context);
        classification::set($this->course->id, [$uid], $this->repeater->get('id'), $this->context);

        $this->assertEquals(1, $DB->count_records('rollcall_usercat', ['courseid' => $this->course->id, 'userid' => $uid]));
        $this->assertSame([$uid => (int)$this->repeater->get('id')],
            $this->ids(classification::get_effective($this->course->id, [$uid])));
    }

    public function test_set_regular_deletes_row(): void {
        global $DB;
        $uid = $this->u[1]->id;
        classification::set($this->course->id, [$uid], $this->erasmus->get('id'), $this->context);

        $result = classification::set($this->course->id, [$uid], 0, $this->context);

        $this->assertSame([$uid => 0], $result);
        $this->assertFalse($DB->record_exists('rollcall_usercat', ['courseid' => $this->course->id, 'userid' => $uid]));
    }

    public function test_classification_is_per_course(): void {
        $uid = $this->u[1]->id;
        $other = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($uid, $other->id, 'student');

        classification::set($this->course->id, [$uid], $this->erasmus->get('id'), $this->context);

        $this->assertSame([$uid => 0], $this->ids(classification::get_effective($other->id, [$uid])));
    }

    public function test_shared_between_instances(): void {
        $uid = $this->u[1]->id;
        $second = $this->getDataGenerator()->create_module('rollcall', ['course' => $this->course->id]);
        $secondcontext = \context_module::instance($second->cmid);

        classification::set($this->course->id, [$uid], $this->erasmus->get('id'), $secondcontext);

        // Effective categories depend only on the course, so the first instance sees the same value.
        $this->assertSame([$uid => (int)$this->erasmus->get('id')],
            $this->ids(classification::get_effective($this->course->id, [$uid])));
    }

    public function test_hidden_category_is_regular_but_kept(): void {
        global $DB;
        $uid = $this->u[1]->id;
        classification::set($this->course->id, [$uid], $this->erasmus->get('id'), $this->context);

        category_manager::set_visible($this->erasmus->get('id'), false);
        $this->assertSame([$uid => 0], $this->ids(classification::get_effective($this->course->id, [$uid])));
        $this->assertTrue($DB->record_exists('rollcall_usercat', ['courseid' => $this->course->id, 'userid' => $uid]));

        category_manager::set_visible($this->erasmus->get('id'), true);
        $this->assertSame([$uid => (int)$this->erasmus->get('id')],
            $this->ids(classification::get_effective($this->course->id, [$uid])));
    }

    public function test_set_rejects_hidden_category(): void {
        category_manager::set_visible($this->erasmus->get('id'), false);
        $this->expectExceptionObject(new \moodle_exception('invalidcategory', 'mod_rollcall'));
        classification::set($this->course->id, [$this->u[1]->id], $this->erasmus->get('id'), $this->context);
    }

    public function test_set_rejects_unknown_category(): void {
        $this->expectExceptionObject(new \moodle_exception('invalidcategory', 'mod_rollcall'));
        classification::set($this->course->id, [$this->u[1]->id], 999999, $this->context);
    }

    public function test_events_only_on_change(): void {
        $u = $this->u;
        $eid = (int)$this->erasmus->get('id');
        classification::set($this->course->id, [$u[2]->id], $eid, $this->context);

        $sink = $this->redirectEvents();
        classification::set($this->course->id, [$u[1]->id, $u[2]->id], $eid, $this->context);
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof user_category_updated));
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertEquals($u[1]->id, $events[0]->relateduserid);
        $this->assertSame(0, $events[0]->other['oldcategoryid']);
        $this->assertSame($eid, $events[0]->other['newcategoryid']);
        $this->assertEquals($this->context->id, $events[0]->contextid);
    }

    public function test_course_deletion_removes_classifications(): void {
        global $DB;
        classification::set($this->course->id, [$this->u[1]->id], $this->erasmus->get('id'), $this->context);
        set_config('categorybinenable', 0, 'tool_recyclebin'); // Delete for real, not to the recycle bin.

        delete_course($this->course, false);

        $this->assertSame(0, $DB->count_records('rollcall_usercat', ['courseid' => $this->course->id]));
    }

    public function test_reset_removes_classifications(): void {
        global $DB;
        classification::set($this->course->id, [$this->u[1]->id], $this->erasmus->get('id'), $this->context);

        reset_course_userdata((object)[
            'id' => $this->course->id,
            'reset_rollcall_classifications' => 1,
            'reset_start_date_old' => 0,
            'reset_end_date_old' => 0,
        ]);

        $this->assertSame(0, $DB->count_records('rollcall_usercat', ['courseid' => $this->course->id]));
    }

    public function test_reset_unticked_keeps_classifications(): void {
        global $DB;
        classification::set($this->course->id, [$this->u[1]->id], $this->erasmus->get('id'), $this->context);

        reset_course_userdata((object)[
            'id' => $this->course->id,
            'reset_rollcall_classifications' => 0,
            'reset_start_date_old' => 0,
            'reset_end_date_old' => 0,
        ]);

        $this->assertSame(1, $DB->count_records('rollcall_usercat', ['courseid' => $this->course->id]));
    }
}
