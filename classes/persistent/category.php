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
use mod_rollcall\local\palette;

/**
 * A student category from the site-wide catalogue.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category extends \core\persistent {
    /** Table name. */
    const TABLE = 'rollcall_category';

    /**
     * Defines the properties of the category.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'color' => [
                'type' => PARAM_ALPHA,
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
     * Validates the colour against the palette.
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_color($value) {
        if (!palette::is_valid((string)$value)) {
            return new lang_string('invalidcolor', 'mod_rollcall');
        }
        return true;
    }
}
