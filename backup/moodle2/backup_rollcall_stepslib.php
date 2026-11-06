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
 * Backup steps for mod_rollcall.
 *
 * @package    mod_rollcall
 * @category   backup
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the structure of a roll backup: the activity instance and its attendance statuses.
 */
class backup_rollcall_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $roll = new backup_nested_element('rollcall', ['id'], ['name', 'intro', 'introformat', 'timecreated', 'timemodified']);
        // The statuses wrapper is always written, so an activity without statuses restores without statuses.
        $statuses = new backup_nested_element('statuses');
        $status = new backup_nested_element('status', ['id'], ['name', 'sortorder', 'visible', 'timecreated', 'timemodified']);
        $roll->add_child($statuses);
        $statuses->add_child($status);

        $roll->set_source_table('rollcall', ['id' => backup::VAR_ACTIVITYID]);
        $status->set_source_table('rollcall_status', ['rollcallid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');
        $roll->annotate_files('mod_rollcall', 'intro', null);
        return $this->prepare_activity_structure($roll);
    }
}
