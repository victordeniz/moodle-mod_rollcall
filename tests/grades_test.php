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

use mod_rollcall\local\attendance_manager;
use mod_rollcall\local\grades;
use mod_rollcall\local\session_manager;
use mod_rollcall\local\status_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/rollcall/lib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Tests for the attendance grades sent to the gradebook.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(grades::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('rollcall_update_grades')]
#[\PHPUnit\Framework\Attributes\CoversFunction('rollcall_rescale_activity_grades')]
final class grades_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \cm_info */
    private \cm_info $cm;
    /** @var \stdClass */
    private \stdClass $one;
    /** @var \stdClass */
    private \stdClass $two;
    /** @var \stdClass */
    private \stdClass $three;
    /** @var \mod_rollcall\persistent\status */
    private $statusp;
    /** @var int[] The three past sessions, oldest first. */
    private array $sessionids = [];

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $this->course->id, 'grade' => 10]);
        $this->cm = get_fast_modinfo($this->course)->get_cm($roll->cmid);
        // Drop the default statuses so the fixture controls the weights.
        $DB->delete_records('rollcall_status', ['rollcallid' => $roll->id]);
        $this->statusp = status_manager::create((int)$roll->id, 'P', 100);
        $statusl = status_manager::create((int)$roll->id, 'L', 50);
        $p = (int)$this->statusp->get('id');
        $l = (int)$statusl->get('id');

        foreach (['one', 'two', 'three'] as $name) {
            $this->$name = $gen->create_and_enrol($this->course, 'student');
        }
        foreach ([1000, 2000, 3000] as $sessdate) {
            $this->sessionids[] = (int)session_manager::create((int)$roll->id, 0, 'S', $sessdate, 3600)->get('id');
        }
        $future = (int)session_manager::create((int)$roll->id, 0, 'F', time() + DAYSECS, 3600)->get('id');

        // One: P, P, L. Two: P in the first session only. Three: nothing in the past.
        attendance_manager::set($this->sessionids[0], $p, [(int)$this->one->id, (int)$this->two->id]);
        attendance_manager::set($this->sessionids[1], $p, [(int)$this->one->id]);
        attendance_manager::set($this->sessionids[2], $l, [(int)$this->one->id]);
        attendance_manager::set($future, $p, [(int)$this->three->id]);
    }

    /**
     * Returns the grade of a user in the gradebook.
     *
     * @param int $userid
     * @return float|null Null without a grade
     */
    private function grade(int $userid): ?float {
        $grades = grade_get_grades($this->course->id, 'mod', 'rollcall', $this->cm->instance, $userid);
        $grade = $grades->items[0]->grades[$userid]->grade ?? null;
        return $grade === null ? null : (float)$grade;
    }

    /**
     * Tests the grade is the report percentage over the maximum grade.
     */
    public function test_sync_sends_the_report_percentage(): void {
        global $DB;
        $this->assertSame(3, grades::sync($this->cm, 0, 5000));
        $this->assertEqualsWithDelta(8.33333, $this->grade((int)$this->one->id), 0.0001);
        $this->assertEqualsWithDelta(3.33333, $this->grade((int)$this->two->id), 0.0001);
        // Unmarked sessions count 0; the future session does not count.
        $this->assertEqualsWithDelta(0.0, $this->grade((int)$this->three->id), 0.0001);
        $this->assertSame(5000, (int)$DB->get_field('rollcall', 'gradessynced', ['id' => $this->cm->instance]));
    }

    /**
     * Tests a student without past sessions gets no grade.
     */
    public function test_no_past_sessions_gives_no_grade(): void {
        // Before the first session nobody has a grade, not a zero.
        $this->assertSame(3, grades::sync($this->cm, 0, 500));
        $this->assertNull($this->grade((int)$this->one->id));
        $this->assertNull($this->grade((int)$this->three->id));
    }

    /**
     * Tests nothing is sent for a roll without a grade.
     */
    public function test_activity_without_grade_sends_nothing(): void {
        global $DB;
        $plain = $this->getDataGenerator()->create_module('rollcall', ['course' => $this->course->id]);
        $cm = get_fast_modinfo($this->course)->get_cm($plain->cmid);
        $this->assertSame(0, grades::sync($cm));
        $this->assertSame(0, (int)$DB->get_field('rollcall', 'gradessynced', ['id' => $plain->id]));
    }

    /**
     * Tests the update of one user, and the students who are no longer listed.
     */
    public function test_single_user_and_students_out_of_the_roster(): void {
        global $DB;
        grades::sync($this->cm, 0, 5000);

        // A student who leaves the roster keeps the grade. The enrolment is suspended, not removed,
        // because the core deletes the grades of a user who is unenrolled.
        $manual = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $manual->update_user_enrol($instance, $this->one->id, ENROL_USER_SUSPENDED);
        attendance_manager::set($this->sessionids[1], (int)$this->statusp->get('id'), [(int)$this->two->id]);
        $this->assertSame(2, grades::sync($this->cm, 0, 6000));
        $this->assertEqualsWithDelta(8.33333, $this->grade((int)$this->one->id), 0.0001);
        $this->assertEqualsWithDelta(6.66667, $this->grade((int)$this->two->id), 0.0001);

        // One user only: the others and the sync time are left alone.
        attendance_manager::set($this->sessionids[0], (int)$this->statusp->get('id'), [(int)$this->three->id]);
        attendance_manager::set($this->sessionids[2], (int)$this->statusp->get('id'), [(int)$this->two->id]);
        $this->assertSame(1, grades::sync($this->cm, (int)$this->three->id, 7000));
        $this->assertEqualsWithDelta(3.33333, $this->grade((int)$this->three->id), 0.0001);
        $this->assertEqualsWithDelta(6.66667, $this->grade((int)$this->two->id), 0.0001);
        $this->assertSame(6000, (int)$DB->get_field('rollcall', 'gradessynced', ['id' => $this->cm->instance]));
    }

    /**
     * Tests a grade overridden in the gradebook is not replaced.
     */
    public function test_overridden_grade_is_kept(): void {
        grades::sync($this->cm, 0, 5000);
        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'rollcall',
            'iteminstance' => $this->cm->instance, 'courseid' => $this->course->id]);
        $item->update_final_grade((int)$this->two->id, 9.0, 'gradebook');
        grades::sync($this->cm, 0, 6000);
        $this->assertEqualsWithDelta(9.0, $this->grade((int)$this->two->id), 0.0001);
    }

    /**
     * Tests the callbacks used by the core give the same grades.
     */
    public function test_core_callbacks_match_the_button(): void {
        global $DB;
        $rollcall = $DB->get_record('rollcall', ['id' => $this->cm->instance], '*', MUST_EXIST);
        rollcall_update_grades($rollcall);
        $this->assertEqualsWithDelta(8.33333, $this->grade((int)$this->one->id), 0.0001);

        // A new maximum resends the grades over it.
        $rollcall->instance = $rollcall->id;
        $rollcall->grade = 20;
        rollcall_update_instance($rollcall);
        $cmrecord = get_coursemodule_from_instance('rollcall', $rollcall->id);
        $this->assertTrue(rollcall_rescale_activity_grades($this->course, $cmrecord, 0, 10, 0, 20));
        $this->assertEqualsWithDelta(16.66667, $this->grade((int)$this->one->id), 0.0001);
    }

    /**
     * Tests a gradebook that refuses the grades is reported, not counted as an update.
     */
    public function test_locked_item_is_reported(): void {
        global $DB;
        $this->assertSame(3, grades::sync($this->cm, 0, 5000));
        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'rollcall',
            'iteminstance' => $this->cm->instance, 'courseid' => $this->course->id]);
        $item->set_locked(1);

        attendance_manager::set($this->sessionids[1], (int)$this->statusp->get('id'), [(int)$this->two->id]);
        $this->assertFalse(grades::sync($this->cm, 0, 6000));
        $this->assertEqualsWithDelta(3.33333, $this->grade((int)$this->two->id), 0.0001);
        $this->assertSame(5000, (int)$DB->get_field('rollcall', 'gradessynced', ['id' => $this->cm->instance]));

        // The core is told that the grades could not be sent again over a new maximum.
        $cmrecord = get_coursemodule_from_instance('rollcall', $this->cm->instance);
        $this->assertFalse(rollcall_rescale_activity_grades($this->course, $cmrecord, 0, 10, 0, 10));
    }

    /**
     * Tests the update a teacher asks for needs the capability.
     */
    public function test_request_update_needs_the_capability(): void {
        global $DB;
        $this->setUser($this->one);
        try {
            grades::request_update($this->cm);
            $this->fail('A student cannot update the grades');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_string('rollcall:updategrades', 'mod_rollcall'), $e->getMessage());
        }
        $this->assertNull($this->grade((int)$this->one->id));
        $this->assertSame(0, (int)$DB->get_field('rollcall', 'gradessynced', ['id' => $this->cm->instance]));
    }

    /**
     * Tests the update a teacher asks for is refused in a roll without a grade.
     */
    public function test_request_update_needs_a_grade(): void {
        $plain = $this->getDataGenerator()->create_module('rollcall', ['course' => $this->course->id]);
        $cm = get_fast_modinfo($this->course)->get_cm($plain->cmid);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('nogradeinactivity', 'mod_rollcall'));
        grades::request_update($cm);
    }

    /**
     * Tests the update a teacher asks for sends the grades of every listed student.
     */
    public function test_request_update_sends_every_grade(): void {
        global $DB;
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->setUser($teacher);
        $this->assertSame(3, grades::request_update($this->cm));
        $this->assertEqualsWithDelta(8.33333, $this->grade((int)$this->one->id), 0.0001);
        $this->assertGreaterThan(0, (int)$DB->get_field('rollcall', 'gradessynced', ['id' => $this->cm->instance]));
    }
}
