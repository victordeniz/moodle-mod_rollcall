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
 * Data generator for mod_rollcall.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_rollcall_generator extends testing_module_generator {
    /**
     * Creates a roll instance.
     *
     * @param array|stdClass|null $record Instance fields
     * @param array|null $options Course module options
     * @return stdClass The instance record, with cmid
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        if (!isset($record->name)) {
            $record->name = 'Roll ' . ($this->instancecount + 1);
        }
        return parent::create_instance($record, (array)$options);
    }
}
