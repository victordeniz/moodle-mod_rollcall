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

namespace mod_rollcall\persistent;

use core\lang_string;

/**
 * An attendance status of a roll.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status extends \core\persistent {
    /** Table name. */
    const TABLE = 'rollcall_status';

    /**
     * Defines the properties of the status.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'rollcallid' => [
                'type' => PARAM_INT,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'visible' => [
                'type' => PARAM_BOOL,
                'default' => true,
            ],
        ];
    }

    /**
     * Validates the name: not blank and unique within the roll, ignoring case and surrounding spaces.
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_name($value) {
        global $DB;

        $normalised = \core_text::strtolower(trim((string)$value));
        if ($normalised === '') {
            return new lang_string('required');
        }
        $others = $DB->get_records_select(
            static::TABLE,
            'rollcallid = :rollcallid AND id <> :id',
            ['rollcallid' => $this->get('rollcallid'), 'id' => (int)$this->get('id')],
            '',
            'id, name'
        );
        foreach ($others as $other) {
            if (\core_text::strtolower(trim($other->name)) === $normalised) {
                return new lang_string('statusnameexists', 'mod_rollcall');
            }
        }
        return true;
    }
}
