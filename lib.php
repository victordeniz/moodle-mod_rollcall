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

/**
 * Library of functions and constants for mod_rollcall.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the features supported by this module.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return bool|string|null True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function rollcall_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ADMINISTRATION;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        default:
            return null;
    }
}

/**
 * Adds a new roll instance.
 *
 * @param stdClass $data Form data
 * @param mod_rollcall_mod_form|null $mform The form
 * @return int The id of the new instance
 */
function rollcall_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->grade = (int)($data->grade ?? 0);
    $id = $DB->insert_record('rollcall', $data);
    $data->id = $id;
    rollcall_grade_item_update($data);

    // The course module does not exist yet, so the defaults are created without events.
    \mod_rollcall\local\status_manager::add_missing_defaults(
        $id,
        \mod_rollcall\local\status_manager::lang_for_course((int)$data->course)
    );
    return $id;
}

/**
 * Updates an existing roll instance.
 *
 * @param stdClass $data Form data
 * @param mod_rollcall_mod_form|null $mform The form
 * @return bool
 */
function rollcall_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $data->grade = (int)($data->grade ?? 0);
    // The time the grades were sent is not a setting of the form.
    unset($data->gradessynced);
    $DB->update_record('rollcall', $data);
    rollcall_grade_item_update($data);
    return true;
}

/**
 * Deletes a roll instance.
 *
 * Classifications belong to the course, not to the instance, so they are kept. The statuses belong to the
 * instance and are deleted with it.
 *
 * @param int $id The instance id
 * @return bool
 */
function rollcall_delete_instance($id): bool {
    global $CFG, $DB;

    $rollcall = $DB->get_record('rollcall', ['id' => $id], 'id, course');
    if (!$rollcall) {
        return false;
    }
    require_once($CFG->libdir . '/gradelib.php');
    grade_update('mod/rollcall', $rollcall->course, 'mod', 'rollcall', $rollcall->id, 0, null, ['deleted' => 1]);
    \mod_rollcall\local\status_manager::delete_for_rollcall((int)$id);
    \mod_rollcall\local\session_manager::delete_for_rollcall((int)$id);
    $DB->delete_records('rollcall', ['id' => $id]);
    return true;
}

/**
 * Creates or updates the grade item of a roll.
 *
 * A roll without a grade keeps an item of type "none", which the gradebook does not show.
 *
 * @param stdClass $rollcall The roll, with id, course, name and grade (and cmidnumber if known)
 * @param array|string|null $grades Grades to send, 'reset' to delete every grade, or null for none
 * @return int GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, GRADE_UPDATE_MULTIPLE or GRADE_UPDATE_ITEM_LOCKED
 */
function rollcall_grade_item_update(stdClass $rollcall, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = ['itemname' => $rollcall->name];
    if (property_exists($rollcall, 'cmidnumber')) {
        $params['idnumber'] = $rollcall->cmidnumber;
    }
    if ((int)$rollcall->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = (int)$rollcall->grade;
        $params['grademin'] = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/rollcall', $rollcall->course, 'mod', 'rollcall', $rollcall->id, 0, $grades, $params);
}

/**
 * Sends the attendance grades of a roll to the gradebook.
 *
 * @param stdClass $rollcall The roll
 * @param int $userid Only this user, or 0 for every listed student
 * @param bool $nullifnone Not used: a student without past sessions never gets a grade
 */
function rollcall_update_grades(stdClass $rollcall, int $userid = 0, bool $nullifnone = true): void {
    $cm = get_coursemodule_from_instance('rollcall', $rollcall->id, $rollcall->course, false, MUST_EXIST);
    $cm = get_fast_modinfo($rollcall->course)->get_cm($cm->id);
    \mod_rollcall\local\grades::sync($cm, $userid);
}

/**
 * Sends the grades again after the maximum grade of a roll has changed.
 *
 * The grades are not scaled from the old ones: they are calculated again over the new maximum.
 *
 * @param stdClass $course
 * @param stdClass $cm Course module of the roll
 * @param float $oldmin
 * @param float $oldmax
 * @param float $newmin
 * @param float $newmax
 * @return bool False when the gradebook did not take the grades (locked item or grades)
 */
function rollcall_rescale_activity_grades($course, $cm, $oldmin, $oldmax, $newmin, $newmax): bool {
    $cm = get_fast_modinfo($course)->get_cm($cm->id);
    return \mod_rollcall\local\grades::sync($cm) !== false;
}

/**
 * Adds the roll elements to the course reset form.
 *
 * @param MoodleQuickForm $mform
 */
function rollcall_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'rollcallheader', get_string('modulenameplural', 'mod_rollcall'));
    $mform->addElement('advcheckbox', 'reset_rollcall_classifications', get_string('resetclassifications', 'mod_rollcall'));
    $mform->addElement('advcheckbox', 'reset_rollcall_attendance', get_string('resetattendance', 'mod_rollcall'));
    $mform->addElement('advcheckbox', 'reset_rollcall_sessions', get_string('resetsessions', 'mod_rollcall'));
}

/**
 * Default values of the roll elements in the course reset form.
 *
 * @param stdClass $course
 * @return array
 */
function rollcall_reset_course_form_defaults($course) {
    return ['reset_rollcall_classifications' => 1, 'reset_rollcall_attendance' => 1, 'reset_rollcall_sessions' => 1];
}

/**
 * Removes the grades of every roll of a course from the gradebook.
 *
 * Called by the core when the gradebook is reset, and by this module when the attendance is.
 *
 * @param int $courseid
 * @param string $type Not used
 */
function rollcall_reset_gradebook($courseid, $type = ''): void {
    global $DB;

    foreach ($DB->get_records('rollcall', ['course' => $courseid]) as $rollcall) {
        rollcall_grade_item_update($rollcall, 'reset');
    }
    $DB->set_field('rollcall', 'gradessynced', 0, ['course' => $courseid]);
}

/**
 * Deletes the course classifications, attendance and sessions when requested by the course reset.
 *
 * @param stdClass $data The reset form data
 * @return array Status array
 */
function rollcall_reset_userdata($data) {
    $status = [];
    // Without the attendance the grades mean nothing. When the core resets the gradebook too it deletes them
    // itself and then asks every roll for its grades again, so they end up as the report would give them now.
    if ((!empty($data->reset_rollcall_attendance) || !empty($data->reset_rollcall_sessions))
            && empty($data->reset_gradebook_grades)) {
        rollcall_reset_gradebook((int)$data->courseid);
    }
    if (!empty($data->reset_rollcall_classifications)) {
        \mod_rollcall\local\classification::delete_for_course((int)$data->courseid);
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_rollcall'),
            'item' => get_string('resetclassifications', 'mod_rollcall'),
            'error' => false,
        ];
    }
    if (!empty($data->reset_rollcall_attendance)) {
        \mod_rollcall\local\attendance_manager::delete_for_course((int)$data->courseid);
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_rollcall'),
            'item' => get_string('resetattendance', 'mod_rollcall'),
            'error' => false,
        ];
    }
    if (!empty($data->reset_rollcall_sessions)) {
        \mod_rollcall\local\session_manager::delete_for_course((int)$data->courseid);
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_rollcall'),
            'item' => get_string('resetsessions', 'mod_rollcall'),
            'error' => false,
        ];
    }
    return $status;
}

/**
 * Adds the students, statuses and report pages to the activity navigation.
 *
 * The students page is shown to users who can view the roll, the statuses page only to those who can manage them
 * and the report only to those who can view it.
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $rollcallnode
 */
function rollcall_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $rollcallnode) {
    global $PAGE;

    if (!$PAGE->cm) {
        return;
    }
    $context = $PAGE->cm->context;
    if (has_capability('mod/rollcall:view', $context)) {
        $rollcallnode->add(
            get_string('students', 'mod_rollcall'),
            new moodle_url('/mod/rollcall/students.php', ['id' => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'mod_rollcall_students'
        );
    }
    if (has_capability('mod/rollcall:managestatuses', $context)) {
        $rollcallnode->add(
            get_string('statuses', 'mod_rollcall'),
            new moodle_url('/mod/rollcall/statuses.php', ['id' => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'mod_rollcall_statuses'
        );
    }
    if (has_capability('mod/rollcall:viewreport', $context)) {
        $rollcallnode->add(
            get_string('report', 'mod_rollcall'),
            new moodle_url('/mod/rollcall/report.php', ['id' => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'mod_rollcall_report'
        );
    }
}
