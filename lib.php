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
            return true;
        case FEATURE_GRADE_HAS_GRADE:
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
    return $DB->insert_record('rollcall', $data);
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
    return $DB->update_record('rollcall', $data);
}

/**
 * Deletes a roll instance.
 *
 * Classifications belong to the course, not to the instance, so they are kept.
 *
 * @param int $id The instance id
 * @return bool
 */
function rollcall_delete_instance($id): bool {
    global $DB;

    if (!$DB->record_exists('rollcall', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('rollcall', ['id' => $id]);
    return true;
}
