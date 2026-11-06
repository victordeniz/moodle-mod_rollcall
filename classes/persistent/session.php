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
 * A session (class meeting) of a roll.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session extends \core\persistent {
    /** Table name. */
    const TABLE = 'rollcall_session';

    /**
     * Defines the properties of the session.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'rollcallid' => [
                'type' => PARAM_INT,
            ],
            'groupid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'sessdate' => [
                'type' => PARAM_INT,
            ],
            'duration' => [
                'type' => PARAM_INT,
            ],
        ];
    }

    /**
     * Validates the name: not blank. Repeated names are allowed.
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_name($value) {
        if (trim((string)$value) === '') {
            return new lang_string('required');
        }
        return true;
    }

    /**
     * Validates the duration: whole minutes, from 1 minute to 24 hours (stored in seconds).
     *
     * @param int $value
     * @return true|lang_string
     */
    protected function validate_duration($value) {
        $value = (int)$value;
        if ($value < 60 || $value > 86400 || $value % 60 !== 0) {
            return new lang_string('invalidduration', 'mod_rollcall');
        }
        return true;
    }
}
