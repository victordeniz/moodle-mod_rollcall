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
namespace mod_rollcall\event;

/**
 * The attendance of a student in a session was changed.
 *
 * @property-read array $other {
 *      - int statusid: the new status, 0 when the status was removed.
 * }
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_updated extends \core\event\base {
    /**
     * Initialises the event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'rollcall_session';
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventattendanceupdated', 'mod_rollcall');
    }

    /**
     * Returns the non-localised event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' updated the attendance of the user with id '$this->relateduserid' " .
            "in the session with id '$this->objectid'.";
    }

    /**
     * Returns the URL of the take attendance page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/rollcall/take.php', ['sessionid' => $this->objectid]);
    }

    /**
     * Maps the object id for backup/restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'rollcall_session', 'restore' => 'rollcall_session'];
    }

    /**
     * Maps 'other' fields for backup/restore.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return ['statusid' => ['db' => 'rollcall_status', 'restore' => 'rollcall_status']];
    }
}
