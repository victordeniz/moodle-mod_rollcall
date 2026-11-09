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
        $status = new backup_nested_element('status', ['id'], ['name', 'sortorder', 'visible', 'weight', 'timecreated', 'timemodified']);
        $roll->add_child($statuses);
        $statuses->add_child($status);
        // Sessions are activity structure, not user data: always written, like the statuses.
        $sessions = new backup_nested_element('sessions');
        $session = new backup_nested_element('session', ['id'],
            ['groupid', 'name', 'sessdate', 'duration', 'timecreated', 'timemodified']);
        $roll->add_child($sessions);
        $sessions->add_child($session);
        // Attendance is personal data: only written with user data.
        $attendances = new backup_nested_element('attendances');
        $attendance = new backup_nested_element('attendance', ['id'],
            ['userid', 'statusid', 'timecreated', 'timemodified']);
        $session->add_child($attendances);
        $attendances->add_child($attendance);

        $roll->set_source_table('rollcall', ['id' => backup::VAR_ACTIVITYID]);
        $status->set_source_table('rollcall_status', ['rollcallid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');
        $session->set_source_table('rollcall_session', ['rollcallid' => backup::VAR_PARENTID], 'sessdate ASC, id ASC');
        $session->annotate_ids('group', 'groupid');
        if ($this->get_setting_value('userinfo')) {
            $attendance->set_source_table('rollcall_attendance', ['sessionid' => backup::VAR_PARENTID], 'id ASC');
            $attendance->annotate_ids('user', 'userid');
        }
        $roll->annotate_files('mod_rollcall', 'intro', null);
        return $this->prepare_activity_structure($roll);
    }
}
