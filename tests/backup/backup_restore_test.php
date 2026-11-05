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

namespace mod_rollcall\backup;

use backup;
use backup_controller;
use backup_setting;
use mod_rollcall\local\classification;
use mod_rollcall\local\status_manager;
use mod_rollcall\persistent\status;
use restore_controller;
use restore_dbops;

/**
 * Tests for the backup and restore of rolls.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Backs up a course with user data and restores it into a new course.
     *
     * @param \stdClass $course
     * @return int The new course id
     */
    private function duplicate_with_userdata(\stdClass $course): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO,
            backup::MODE_IMPORT, $USER->id);
        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = restore_dbops::create_new_course($course->fullname, $course->shortname . '_copy', $course->category);
        $rc = new restore_controller($backupid, $newcourseid, backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id,
            backup::TARGET_NEW_COURSE);
        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    public function test_duplicate_course_does_not_copy_classifications(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id, 'name' => 'Class roll', 'intro' => 'Intro']);
        $student = $gen->create_and_enrol($course, 'student');
        $category = $gen->get_plugin_generator('mod_rollcall')->create_category();
        classification::set($course->id, [$student->id], $category->get('id'), \context_module::instance($roll->cmid));

        $newcourseid = $this->duplicate_with_userdata($course);

        $restored = $DB->get_records('rollcall', ['course' => $newcourseid]);
        $this->assertCount(1, $restored);
        $this->assertSame('Class roll', reset($restored)->name);
        $this->assertSame('Intro', reset($restored)->intro);
        $this->assertSame(0, $DB->count_records('rollcall_usercat', ['courseid' => $newcourseid]));
        $this->assertSame(1, $DB->count_records('rollcall_usercat', ['courseid' => $course->id]));
    }

    public function test_course_deletion_with_recycle_bin(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('categorybinenable', 1, 'tool_recyclebin');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $category = $gen->get_plugin_generator('mod_rollcall')->create_category();
        classification::set($course->id, [$student->id], $category->get('id'), \context_module::instance($roll->cmid));

        delete_course($course, false);

        $this->assertFalse($DB->record_exists('course', ['id' => $course->id]));
        $this->assertSame(0, $DB->count_records('rollcall_usercat', ['courseid' => $course->id]));
    }

    /**
     * Returns [name, visible] of the statuses of a roll, in order.
     *
     * @param int $rollcallid
     * @return array
     */
    private function status_summary(int $rollcallid): array {
        return array_values(array_map(fn(status $s) => [$s->get('name'), (bool)$s->get('visible')],
            status_manager::get_all($rollcallid)));
    }

    public function test_backup_restore_keeps_statuses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $all = array_values(status_manager::get_all((int)$roll->id));
        status_manager::update((int)$all[0]->get('id'), 'Excused');
        status_manager::set_visible((int)$all[1]->get('id'), false);
        status_manager::move((int)$all[2]->get('id'), -1);

        $newcourseid = $this->duplicate_with_userdata($course);

        $restored = $DB->get_record('rollcall', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertNotEquals($roll->id, $restored->id);
        $this->assertSame($this->status_summary((int)$roll->id), $this->status_summary((int)$restored->id));
        $this->assertCount(3, status_manager::get_all((int)$restored->id));
        // The original is untouched and the ids are new.
        $this->assertSame([], array_intersect(
            array_keys(status_manager::get_all((int)$roll->id)),
            array_keys(status_manager::get_all((int)$restored->id))
        ));
    }

    public function test_restore_of_activity_without_statuses_stays_empty(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        status_manager::delete_for_rollcall((int)$roll->id);

        $newcourseid = $this->duplicate_with_userdata($course);

        $restored = $DB->get_record('rollcall', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame([], status_manager::get_all((int)$restored->id));
    }

    public function test_duplicate_activity_copies_statuses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $first = array_values(status_manager::get_all((int)$roll->id))[0];
        status_manager::update((int)$first->get('id'), 'Excused');

        $newcm = (new \core_courseformat\local\cmactions($course))->duplicate((int)$roll->cmid);

        $this->assertSame($this->status_summary((int)$roll->id), $this->status_summary((int)$newcm->instance));
        $this->assertCount(3, status_manager::get_all((int)$newcm->instance));
    }
}
