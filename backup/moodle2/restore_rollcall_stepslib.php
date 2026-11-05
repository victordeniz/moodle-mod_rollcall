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
 * Restore steps for mod_rollcall.
 *
 * @package    mod_rollcall
 * @category   backup
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores the roll instance and its attendance statuses.
 */
class restore_rollcall_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the restore paths.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        return $this->prepare_activity_structure([
            new restore_path_element('rollcall', '/activity/rollcall'),
            new restore_path_element('rollcall_status', '/activity/rollcall/statuses/status'),
        ]);
    }

    /**
     * Restores the instance record.
     *
     * @param array $data
     */
    protected function process_rollcall($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $newitemid = $DB->insert_record('rollcall', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restores an attendance status of the roll, with a new id.
     *
     * A name that collides with one already restored gets a numeric suffix instead of failing the restore.
     *
     * @param array $data
     */
    protected function process_rollcall_status($data) {
        global $DB;
        $data = (object)$data;
        $data->rollcallid = $this->get_new_parentid('rollcall');

        $taken = array_map(fn($name) => \core_text::strtolower(trim($name)),
            $DB->get_fieldset_select('rollcall_status', 'name', 'rollcallid = ?', [$data->rollcallid]));
        $base = $data->name;
        $suffix = 1;
        while (in_array(\core_text::strtolower(trim($data->name)), $taken, true)) {
            $suffix++;
            $data->name = $base . ' (' . $suffix . ')';
        }
        unset($data->id);
        $DB->insert_record('rollcall_status', $data);
    }

    /**
     * Restores the intro files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_rollcall', 'intro', null);
    }
}
