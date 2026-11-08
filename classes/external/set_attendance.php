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

namespace mod_rollcall\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_rollcall\local\attendance_manager;
use mod_rollcall\local\roster;
use mod_rollcall\local\session_manager;
use mod_rollcall\local\status_manager;

/**
 * Sets the attendance status of one or more students in a session.
 *
 * The whole batch is rejected, with no change, if the user cannot take attendance of the session,
 * the status is not assignable, or any user is not listed in the session roster.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_attendance extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the roll'),
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
            'statusid' => new external_value(PARAM_INT, 'Status id, or 0 to clear the attendance'),
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User id'),
                'Students whose attendance is set'
            ),
        ]);
    }

    /**
     * Sets the status of the students in the session.
     *
     * @param int $cmid
     * @param int $sessionid
     * @param int $statusid
     * @param int[] $userids
     * @return array List of ['userid' => int, 'statusid' => int]
     */
    public static function execute(int $cmid, int $sessionid, int $statusid, array $userids): array {
        [
            'cmid' => $cmid,
            'sessionid' => $sessionid,
            'statusid' => $statusid,
            'userids' => $userids,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'sessionid' => $sessionid,
            'statusid' => $statusid,
            'userids' => $userids,
        ]);

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'rollcall');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/rollcall:takeattendance', $context);

        $session = session_manager::get_for_rollcall($sessionid, (int)$cm->instance);
        if (!session_manager::can_take($cm, $session)) {
            throw new \required_capability_exception($context, 'mod/rollcall:takeattendance', 'nopermissions', '');
        }

        if ($statusid > 0) {
            $status = status_manager::get_for_rollcall($statusid, (int)$cm->instance);
            if (!$status->get('visible')) {
                throw new \moodle_exception('invalidstatus', 'mod_rollcall');
            }
        }

        $userids = array_values(array_unique(array_map('intval', $userids)));
        $groupid = session_manager::roster_groupid($cm, $session);
        if (count(roster::filter_listed($cm, $userids, $groupid)) !== count($userids)) {
            throw new \moodle_exception('usernotlisted', 'mod_rollcall');
        }

        $result = attendance_manager::set($sessionid, $statusid, $userids, $context);
        $return = [];
        foreach ($result as $userid => $newstatusid) {
            $return[] = ['userid' => (int)$userid, 'statusid' => (int)$newstatusid];
        }
        return $return;
    }

    /**
     * Describes the return value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User id'),
                'statusid' => new external_value(PARAM_INT, 'Resulting status id, 0 for none'),
            ])
        );
    }
}
