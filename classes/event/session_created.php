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
 * A session created.
 *
 * @property-read array $other {
 *      - string name: the session name.
 *      - int groupid: the group of the session, 0 for the whole activity.
 * }
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_created extends \core\event\base {
    /**
     * Initialises the event data.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'rollcall_session';
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsessioncreated', 'mod_rollcall');
    }

    /**
     * Returns the non-localised event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' created the session with id '$this->objectid'.";
    }

    /**
     * Returns the URL of the roll page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/rollcall/view.php', ['id' => $this->contextinstanceid]);
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
        return ['groupid' => ['db' => 'groups', 'restore' => 'group']];
    }
}
