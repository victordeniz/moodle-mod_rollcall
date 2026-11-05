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
     * The module declares the expected features.
     */
    public function test_supports(): void {
        $this->assertSame(MOD_PURPOSE_ADMINISTRATION, rollcall_supports(FEATURE_MOD_PURPOSE));
        $this->assertTrue(rollcall_supports(FEATURE_GROUPS));
        $this->assertTrue(rollcall_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertFalse(rollcall_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertFalse(rollcall_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertNull(rollcall_supports(FEATURE_RATE));
    }
}
