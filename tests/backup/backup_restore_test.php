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
use mod_rollcall\local\attendance_manager;
use mod_rollcall\local\classification;
use mod_rollcall\local\session_manager;
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
     * @param array $restoresettings Restore plan setting values by name, applied before the precheck
     * @param callable|null $beforerestore Called with the backup id between the backup and the restore
     * @return int The new course id
     */
    private function duplicate_with_userdata(\stdClass $course, array $restoresettings = [],
            ?callable $beforerestore = null): int {
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
        if ($beforerestore) {
            $beforerestore($backupid);
        }

        $newcourseid = restore_dbops::create_new_course($course->fullname, $course->shortname . '_copy', $course->category);
        $rc = new restore_controller($backupid, $newcourseid, backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id,
            backup::TARGET_NEW_COURSE);
        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);
        foreach ($restoresettings as $name => $value) {
            $rc->get_plan()->get_setting($name)->set_status(backup_setting::NOT_LOCKED);
            $rc->get_plan()->get_setting($name)->set_value($value);
        }
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
        status_manager::update((int)$all[0]->get('id'), 'Excused', 0);
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

    public function test_backup_restore_keeps_weights(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $default = array_values(status_manager::get_all((int)$roll->id))[0];
        status_manager::update((int)$default->get('id'), $default->get('name'), 20);
        status_manager::create((int)$roll->id, 'Custom', 70);

        $newcourseid = $this->duplicate_with_userdata($course);

        $restored = $DB->get_record('rollcall', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame([20, 50, 0, 70], $this->weights((int)$restored->id));
    }

    public function test_restore_old_backup_without_weight(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['lang' => 'en']);
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        status_manager::create((int)$roll->id, 'Custom', 70);

        // Backups made before statuses had a weight have no weight element.
        $newcourseid = $this->duplicate_with_userdata($course, [], function(string $backupid) use ($CFG): void {
            $files = glob($CFG->tempdir . '/backup/' . $backupid . '/activities/rollcall_*/rollcall.xml');
            $this->assertCount(1, $files);
            $xml = file_get_contents($files[0]);
            $this->assertStringContainsString('<weight>', $xml);
            file_put_contents($files[0], preg_replace('~<weight>[^<]*</weight>~', '', $xml));
        });

        $restored = $DB->get_record('rollcall', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame([100, 50, 0, 0], $this->weights((int)$restored->id));
    }

    public function test_restore_clamps_weight_out_of_range(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['lang' => 'en']);
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        status_manager::create((int)$roll->id, 'Custom', 70);

        // A hand-edited backup must not produce weights outside 0-100.
        $newcourseid = $this->duplicate_with_userdata($course, [], function(string $backupid) use ($CFG): void {
            $files = glob($CFG->tempdir . '/backup/' . $backupid . '/activities/rollcall_*/rollcall.xml');
            $xml = file_get_contents($files[0]);
            $xml = str_replace('<weight>70</weight>', '<weight>150</weight>', $xml);
            file_put_contents($files[0], str_replace('<weight>0</weight>', '<weight>-5</weight>', $xml));
        });

        $restored = $DB->get_record('rollcall', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame([100, 50, 0, 100], $this->weights((int)$restored->id));
    }

    /**
     * Returns the weights of a roll's statuses in display order.
     *
     * @param int $rollcallid
     * @return int[]
     */
    private function weights(int $rollcallid): array {
        return array_values(array_map(fn(status $s) => (int)$s->get('weight'), status_manager::get_all($rollcallid)));
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
        status_manager::update((int)$first->get('id'), 'Excused', 0);

        $newcm = (new \core_courseformat\local\cmactions($course))->duplicate((int)$roll->cmid);

        $this->assertSame($this->status_summary((int)$roll->id), $this->status_summary((int)$newcm->instance));
        $this->assertCount(3, status_manager::get_all((int)$newcm->instance));
    }

    /**
     * Returns the sessions of a roll keyed by name, as [groupid, sessdate, duration].
     *
     * @param int $rollcallid
     * @return array
     */
    private function sessions_by_name(int $rollcallid): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('rollcall_session', ['rollcallid' => $rollcallid]) as $s) {
            $out[$s->name] = [(int)$s->groupid, (int)$s->sessdate, (int)$s->duration];
        }
        return $out;
    }

    public function test_backup_restore_keeps_sessions_and_maps_groups(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $group = $gen->create_group(['courseid' => $course->id, 'name' => 'G1']);
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        session_manager::create((int)$roll->id, 0, 'Whole', 1700000000, 3600);
        session_manager::create((int)$roll->id, (int)$group->id, 'Grouped', 1700100000, 1800);

        $newcourseid = $this->duplicate_with_userdata($course);

        $restored = $DB->get_record('rollcall', ['course' => $newcourseid], '*', MUST_EXIST);
        $new = $this->sessions_by_name((int)$restored->id);
        $newgroup = $DB->get_record('groups', ['courseid' => $newcourseid, 'name' => 'G1'], '*', MUST_EXIST);
        $this->assertNotEquals($group->id, $newgroup->id);
        $this->assertSame(['Whole' => [0, 1700000000, 3600], 'Grouped' => [(int)$newgroup->id, 1700100000, 1800]], $new);
        // The original is untouched.
        $this->assertSame(['Whole' => [0, 1700000000, 3600], 'Grouped' => [(int)$group->id, 1700100000, 1800]],
            $this->sessions_by_name((int)$roll->id));
    }

    public function test_restore_into_other_course_without_groups(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $group = $gen->create_group(['courseid' => $course->id, 'name' => 'G1']);
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        session_manager::create((int)$roll->id, (int)$group->id, 'Grouped', 1700100000, 1800);

        $newcourseid = $this->duplicate_with_userdata($course, ['groups' => false]);

        $restored = $DB->get_record('rollcall', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame(0, $DB->count_records('groups', ['courseid' => $newcourseid]));
        $this->assertSame(['Grouped' => [0, 1700100000, 1800]], $this->sessions_by_name((int)$restored->id));
    }

    public function test_restore_shifts_session_dates(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $start = 1700000000;
        $shift = 365 * DAYSECS;
        $course = $gen->create_course(['startdate' => $start]);
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        session_manager::create((int)$roll->id, 0, 'Whole', $start + 2 * DAYSECS, 3600);

        $newcourseid = $this->duplicate_with_userdata($course, ['course_startdate' => $start + $shift]);

        $restored = $DB->get_record('rollcall', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame(['Whole' => [0, $start + 2 * DAYSECS + $shift, 3600]],
            $this->sessions_by_name((int)$restored->id));
    }

    public function test_duplicate_activity_keeps_session_groups(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $group = $gen->create_group(['courseid' => $course->id, 'name' => 'G1']);
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        session_manager::create((int)$roll->id, 0, 'Whole', 1700000000, 3600);
        session_manager::create((int)$roll->id, (int)$group->id, 'Grouped', 1700100000, 1800);

        $newcm = (new \core_courseformat\local\cmactions($course))->duplicate((int)$roll->cmid);

        $this->assertSame(['Whole' => [0, 1700000000, 3600], 'Grouped' => [(int)$group->id, 1700100000, 1800]],
            $this->sessions_by_name((int)$newcm->instance));
    }

    public function test_backup_restore_attendance_with_userinfo(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $session = session_manager::create((int)$roll->id, 0, 'Whole', 1700000000, 3600);
        $statuses = array_values(status_manager::get_all((int)$roll->id));
        attendance_manager::set((int)$session->get('id'), (int)$statuses[1]->get('id'), [(int)$student->id]);

        $newcourseid = $this->duplicate_with_userdata($course);

        $restored = $DB->get_record('rollcall', ['course' => $newcourseid], '*', MUST_EXIST);
        $newsession = $DB->get_record('rollcall_session', ['rollcallid' => $restored->id], '*', MUST_EXIST);
        $rows = $DB->get_records('rollcall_attendance', ['sessionid' => $newsession->id]);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEquals($student->id, $row->userid);
        $newstatus = $DB->get_record('rollcall_status', ['id' => $row->statusid], '*', MUST_EXIST);
        $this->assertEquals($restored->id, $newstatus->rollcallid);
        $this->assertSame($statuses[1]->get('name'), $newstatus->name);
        // The original is untouched.
        $this->assertSame(1, attendance_manager::count_for_session((int)$session->get('id')));
    }

    public function test_backup_without_userinfo_has_no_attendance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $session = session_manager::create((int)$roll->id, 0, 'Whole', 1700000000, 3600);
        $statuses = array_values(status_manager::get_all((int)$roll->id));
        attendance_manager::set((int)$session->get('id'), (int)$statuses[0]->get('id'), [(int)$student->id]);

        $newcm = (new \core_courseformat\local\cmactions($course))->duplicate((int)$roll->cmid);

        $this->assertSame(['Whole' => [0, 1700000000, 3600]], $this->sessions_by_name((int)$newcm->instance));
        $this->assertCount(3, status_manager::get_all((int)$newcm->instance));
        $newsession = $DB->get_record('rollcall_session', ['rollcallid' => $newcm->instance], '*', MUST_EXIST);
        $this->assertSame(0, attendance_manager::count_for_session((int)$newsession->id));
    }
}
