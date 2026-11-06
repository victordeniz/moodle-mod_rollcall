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
 * The effective category of a student changed.
 *
 * @property-read array $other {
 *      - int oldcategoryid: previous category id, 0 for regular.
 *      - int newcategoryid: new category id, 0 for regular.
 * }
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_category_updated extends \core\event\base {
    /**
     * Initialises the event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventusercategoryupdated', 'mod_rollcall');
    }

    /**
     * Returns the non-localised event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' changed the category of the user with id '$this->relateduserid' " .
            "from '{$this->other['oldcategoryid']}' to '{$this->other['newcategoryid']}' " .
            "in the roll with course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns the URL of the roll.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/rollcall/students.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Validates the event data.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        foreach (['oldcategoryid', 'newcategoryid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
        if ($this->contextlevel !== CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    /**
     * Maps 'other' fields for backup/restore.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'oldcategoryid' => ['db' => 'rollcall_category', 'restore' => \core\event\base::NOT_MAPPED],
            'newcategoryid' => ['db' => 'rollcall_category', 'restore' => \core\event\base::NOT_MAPPED],
        ];
    }
}
