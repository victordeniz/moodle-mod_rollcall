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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/rollcall/lib.php');

/**
 * Tests for the mod_rollcall library callbacks.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('rollcall_add_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('rollcall_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('rollcall_supports')]
final class lib_test extends \advanced_testcase {
    /**
     * Deleting an instance removes it but keeps the course classifications.
     */
    public function test_instance_lifecycle_keeps_classifications(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('coursebinenable', 0, 'tool_recyclebin'); // Delete for real, not to the recycle bin.

        $course = $this->getDataGenerator()->create_course();
        $roll = $this->getDataGenerator()->create_module('rollcall', ['course' => $course->id]);
        $this->assertTrue($DB->record_exists('rollcall', ['id' => $roll->id]));

        $DB->insert_record('rollcall_usercat', (object)[
            'courseid' => $course->id, 'userid' => 2, 'categoryid' => 1,
            'usermodified' => 2, 'timecreated' => 1, 'timemodified' => 1,
        ]);

        (new \core_courseformat\local\cmactions($course))->delete($roll->cmid);

        $this->assertFalse($DB->record_exists('rollcall', ['id' => $roll->id]));
        $this->assertEquals(1, $DB->count_records('rollcall_usercat', ['courseid' => $course->id]));
    }

    /**
     * A new activity gets the default statuses in the course language; deleting it removes them;
     * the course reset keeps them.
     */
    public function test_add_instance_seeds_defaults_and_delete_removes_them(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('coursebinenable', 0, 'tool_recyclebin');

        $course = $this->getDataGenerator()->create_course();
        $roll = $this->getDataGenerator()->create_module('rollcall', ['course' => $course->id]);
        $lang = \mod_rollcall\local\status_manager::lang_for_course($course->id);
        $names = fn() => array_values(array_map(fn($s) => $s->get('name'),
            \mod_rollcall\local\status_manager::get_all((int)$roll->id)));
        $this->assertSame(\mod_rollcall\local\status_manager::default_names($lang), $names());
        $this->assertSame(3, count(\mod_rollcall\local\status_manager::get_visible((int)$roll->id)));

        // A course with a forced language gets the names in that language.
        $forced = $this->getDataGenerator()->create_course(['lang' => 'es']);
        $this->assertSame('es', \mod_rollcall\local\status_manager::lang_for_course($forced->id));

        // The course reset does not touch them.
        rollcall_reset_userdata((object)['courseid' => $course->id, 'reset_rollcall_classifications' => 1]);
        $this->assertCount(3, $names());

        (new \core_courseformat\local\cmactions($course))->delete($roll->cmid);
        $this->assertSame(0, $DB->count_records('rollcall_status', ['rollcallid' => $roll->id]));
    }

    /**
     * Deleting an activity removes only its sessions; the course reset removes those of the whole course.
     */
    public function test_delete_instance_and_course_reset_remove_sessions(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('coursebinenable', 0, 'tool_recyclebin');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $rollone = $gen->create_module('rollcall', ['course' => $course->id]);
        $polltwo = $gen->create_module('rollcall', ['course' => $course->id]);
        $rollother = $gen->create_module('rollcall', ['course' => $other->id]);
        foreach ([$rollone, $polltwo, $rollother] as $roll) {
            \mod_rollcall\local\session_manager::create((int)$roll->id, 0, 'S', 1000, 3600);
        }
        $count = fn($roll) => $DB->count_records('rollcall_session', ['rollcallid' => $roll->id]);

        (new \core_courseformat\local\cmactions($course))->delete($rollone->cmid);
        $this->assertSame(0, $count($rollone));
        $this->assertSame(1, $count($polltwo));
        $this->assertSame(1, $count($rollother));

        // Without the flag nothing is deleted.
        rollcall_reset_userdata((object)['courseid' => $course->id, 'reset_rollcall_sessions' => 0]);
        $this->assertSame(1, $count($polltwo));

        $status = rollcall_reset_userdata((object)['courseid' => $course->id, 'reset_rollcall_sessions' => 1]);
        $this->assertSame(0, $count($polltwo));
        $this->assertSame(1, $count($rollother));
        $this->assertCount(1, $status);
        $this->assertSame(
            ['reset_rollcall_sessions' => 1],
            array_intersect_key(rollcall_reset_course_form_defaults($course), ['reset_rollcall_sessions' => 1])
        );
    }

    /**
     * Deleting a session or an activity removes its attendance.
     */
    public function test_deleting_session_and_instance_removes_attendance(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('coursebinenable', 0, 'tool_recyclebin');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $user = $gen->create_and_enrol($course, 'student');
        $statusid = (int)array_values(\mod_rollcall\local\status_manager::get_all((int)$roll->id))[0]->get('id');
        $s1 = (int)\mod_rollcall\local\session_manager::create((int)$roll->id, 0, 'S1', 1000, 3600)->get('id');
        $s2 = (int)\mod_rollcall\local\session_manager::create((int)$roll->id, 0, 'S2', 5000, 3600)->get('id');
        foreach ([$s1, $s2] as $s) {
            \mod_rollcall\local\attendance_manager::set($s, $statusid, [(int)$user->id]);
        }

        \mod_rollcall\local\session_manager::delete($s1);
        $this->assertSame(0, $DB->count_records('rollcall_attendance', ['sessionid' => $s1]));
        $this->assertSame(1, $DB->count_records('rollcall_attendance', ['sessionid' => $s2]));

        (new \core_courseformat\local\cmactions($course))->delete($roll->cmid);
        $this->assertSame(0, $DB->count_records('rollcall_attendance'));
    }

    /**
     * The course reset can delete only the attendance, or the sessions together with it.
     */
    public function test_course_reset_attendance_options(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $user = $gen->create_and_enrol($course, 'student');
        $statusid = (int)array_values(\mod_rollcall\local\status_manager::get_all((int)$roll->id))[0]->get('id');
        $s = (int)\mod_rollcall\local\session_manager::create((int)$roll->id, 0, 'S', 1000, 3600)->get('id');
        \mod_rollcall\local\attendance_manager::set($s, $statusid, [(int)$user->id]);

        $status = rollcall_reset_userdata((object)['courseid' => $course->id, 'reset_rollcall_attendance' => 1]);
        $this->assertSame(0, $DB->count_records('rollcall_attendance'));
        $this->assertSame(1, $DB->count_records('rollcall_session', ['rollcallid' => $roll->id]));
        $this->assertCount(1, $status);

        \mod_rollcall\local\attendance_manager::set($s, $statusid, [(int)$user->id]);
        rollcall_reset_userdata((object)['courseid' => $course->id, 'reset_rollcall_sessions' => 1]);
        $this->assertSame(0, $DB->count_records('rollcall_attendance'));
        $this->assertSame(0, $DB->count_records('rollcall_session', ['rollcallid' => $roll->id]));

        $this->assertSame(1, rollcall_reset_course_form_defaults($course)['reset_rollcall_attendance']);
    }

    /**
     * Students do not see the activity; teachers do (core hides modules without mod/rollcall:view).
     */
    public function test_cm_hidden_for_students(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'teacher');

        $this->assertFalse(get_fast_modinfo($course, $student->id)->get_cm($roll->cmid)->uservisible);
        $this->assertTrue(get_fast_modinfo($course, $teacher->id)->get_cm($roll->cmid)->uservisible);
    }

    /**
     * Teachers, with and without editing rights, can view the attendance report; students cannot.
     */
    public function test_report_capability(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $context = \context_module::instance($roll->cmid);

        foreach (['teacher' => true, 'editingteacher' => true, 'student' => false] as $role => $expected) {
            $user = $gen->create_and_enrol($course, $role);
            $this->assertSame($expected, has_capability('mod/rollcall:viewreport', $context, $user), $role);
        }
    }

    /**
     * The module declares the expected features.
     */
    public function test_supports(): void {
        $this->assertSame(MOD_PURPOSE_ADMINISTRATION, rollcall_supports(FEATURE_MOD_PURPOSE));
        $this->assertTrue(rollcall_supports(FEATURE_GROUPS));
        $this->assertTrue(rollcall_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(rollcall_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertFalse(rollcall_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertNull(rollcall_supports(FEATURE_RATE));
    }

    /**
     * Tests the grade item follows the grade setting of the activity.
     */
    public function test_grade_item_lifecycle(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $fetch = fn(int $id) => \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'rollcall',
            'iteminstance' => $id, 'itemnumber' => 0, 'courseid' => $course->id]);

        // No grade by default.
        $plain = $gen->create_module('rollcall', ['course' => $course->id]);
        $this->assertSame(0, (int)$DB->get_field('rollcall', 'grade', ['id' => $plain->id]));
        $this->assertSame(0, (int)$DB->get_field('rollcall', 'gradessynced', ['id' => $plain->id]));
        $item = $fetch((int)$plain->id);
        $this->assertTrue(!$item || (int)$item->gradetype === GRADE_TYPE_NONE);

        // Points create a value item with that maximum.
        $graded = $gen->create_module('rollcall', ['course' => $course->id, 'grade' => 10]);
        $item = $fetch((int)$graded->id);
        $this->assertSame(GRADE_TYPE_VALUE, (int)$item->gradetype);
        $this->assertEqualsWithDelta(10.0, (float)$item->grademax, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float)$item->grademin, 0.0001);

        // Editing the activity follows the setting.
        $record = $DB->get_record('rollcall', ['id' => $graded->id]);
        $record->instance = $record->id;
        $record->grade = 0;
        rollcall_update_instance($record);
        $this->assertSame(GRADE_TYPE_NONE, (int)$fetch((int)$graded->id)->gradetype);

        // Deleting the activity deletes its item.
        (new \core_courseformat\local\cmactions($course))->delete($graded->cmid);
        $this->assertFalse($fetch((int)$graded->id));
    }

    /**
     * Tests who can update the grades.
     */
    public function test_updategrades_capability(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $context = \context_module::instance($roll->cmid);
        foreach (['editingteacher' => true, 'teacher' => true, 'student' => false] as $role => $expected) {
            $user = $gen->create_and_enrol($course, $role);
            $this->assertSame($expected, has_capability('mod/rollcall:updategrades', $context, $user));
        }
    }

    /**
     * Tests the grades go away with the attendance in a course reset, and only then.
     */
    public function test_course_reset_clears_grades(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id, 'grade' => 10]);
        $cm = get_fast_modinfo($course)->get_cm($roll->cmid);
        $user = $gen->create_and_enrol($course, 'student');
        // The first default status is worth a whole session.
        $statusid = (int)array_values(\mod_rollcall\local\status_manager::get_all((int)$roll->id))[0]->get('id');
        $s = (int)\mod_rollcall\local\session_manager::create((int)$roll->id, 0, 'S', 1000, 3600)->get('id');
        \mod_rollcall\local\attendance_manager::set($s, $statusid, [(int)$user->id]);
        \mod_rollcall\local\grades::sync($cm, 0, 5000);
        $grade = fn() => grade_get_grades($course->id, 'mod', 'rollcall', $roll->id, $user->id)->items[0]->grades[$user->id]->grade;
        $this->assertEqualsWithDelta(10.0, (float)$grade(), 0.0001);

        // Resetting something else keeps the grades.
        rollcall_reset_userdata((object)['courseid' => $course->id, 'reset_rollcall_classifications' => 1]);
        $this->assertEqualsWithDelta(10.0, (float)$grade(), 0.0001);
        $this->assertSame(5000, (int)$DB->get_field('rollcall', 'gradessynced', ['id' => $roll->id]));

        // Without the attendance the grades mean nothing.
        rollcall_reset_userdata((object)['courseid' => $course->id, 'reset_rollcall_attendance' => 1]);
        $this->assertNull($grade());
        $this->assertSame(0, (int)$DB->get_field('rollcall', 'gradessynced', ['id' => $roll->id]));
        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'rollcall', 'iteminstance' => $roll->id,
            'courseid' => $course->id]);
        $this->assertSame(GRADE_TYPE_VALUE, (int)$item->gradetype);
    }

    /**
     * Tests what the gradebook holds when the core resets it too and asks the rolls for their grades again.
     */
    public function test_course_reset_with_core_gradebook_reset(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id, 'grade' => 10]);
        $cm = get_fast_modinfo($course)->get_cm($roll->cmid);
        $user = $gen->create_and_enrol($course, 'student');
        $statusid = (int)array_values(\mod_rollcall\local\status_manager::get_all((int)$roll->id))[0]->get('id');
        $s = (int)\mod_rollcall\local\session_manager::create((int)$roll->id, 0, 'S', 1000, 3600)->get('id');
        \mod_rollcall\local\attendance_manager::set($s, $statusid, [(int)$user->id]);
        \mod_rollcall\local\grades::sync($cm, 0, 5000);
        $grade = fn() => grade_get_grades($course->id, 'mod', 'rollcall', $roll->id, $user->id)->items[0]->grades[$user->id]->grade;

        // The attendance goes but the past session stays: the core fetches the grades again, and a student who is
        // still enrolled gets what the report says, a 0.
        rollcall_reset_userdata((object)['courseid' => $course->id, 'reset_rollcall_attendance' => 1,
            'reset_gradebook_grades' => 1]);
        grade_course_reset($course->id);
        $this->assertEqualsWithDelta(0.0, (float)$grade(), 0.0001);
        $this->assertNotNull($grade());

        // Without sessions nobody has a grade.
        rollcall_reset_userdata((object)['courseid' => $course->id, 'reset_rollcall_sessions' => 1,
            'reset_gradebook_grades' => 1]);
        grade_course_reset($course->id);
        $this->assertNull($grade());
    }
}
