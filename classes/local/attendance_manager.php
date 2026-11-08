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

namespace mod_rollcall\local;

use mod_rollcall\event\attendance_updated;
use mod_rollcall\persistent\attendance;

/**
 * Records the attendance of the students in a session.
 *
 * A student without a row in the attendance table has no status (status id 0).
 * Events are triggered only when the module context is given.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_manager {
    /**
     * Returns the recorded status of some students in a session.
     *
     * @param int $sessionid
     * @param int[] $userids
     * @return int[] Status id by user id, only for the users that have a record
     */
    public static function get_for_users(int $sessionid, array $userids): array {
        global $DB;

        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params['sessionid'] = $sessionid;
        $rows = $DB->get_records_select(
            attendance::TABLE,
            "sessionid = :sessionid AND userid $insql",
            $params,
            '',
            'userid, statusid'
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row->userid] = (int)$row->statusid;
        }
        return $result;
    }

    /**
     * Sets the status of some students in a session; 0 removes the record.
     *
     * Neither the permissions nor the statuses are checked here.
     *
     * @param int $sessionid
     * @param int $statusid The status, or 0 for no status
     * @param int[] $userids
     * @param \context_module|null $context When given, an event is triggered for every changed student
     * @return int[] The resulting status id of every given user
     */
    public static function set(int $sessionid, int $statusid, array $userids, ?\context_module $context = null): array {
        global $DB;

        $userids = array_values(array_unique(array_map('intval', $userids)));
        $transaction = $DB->start_delegated_transaction();
        $current = self::get_for_users($sessionid, $userids);
        $result = [];
        foreach ($userids as $userid) {
            $result[$userid] = $statusid;
            $before = $current[$userid] ?? 0;
            if ($before === $statusid) {
                continue;
            }

            if ($statusid === 0) {
                $DB->delete_records(attendance::TABLE, ['sessionid' => $sessionid, 'userid' => $userid]);
            } else {
                $record = $before === 0 ? false : attendance::get_record(['sessionid' => $sessionid, 'userid' => $userid]);
                if (!$record) {
                    // No row (or it vanished meanwhile): create it, unless a concurrent request did first.
                    try {
                        (new attendance(0, (object)[
                            'sessionid' => $sessionid,
                            'userid' => $userid,
                            'statusid' => $statusid,
                        ]))->create();
                    } catch (\dml_write_exception $e) {
                        $record = attendance::get_record(['sessionid' => $sessionid, 'userid' => $userid]);
                        if (!$record) {
                            throw $e;
                        }
                    }
                }
                if ($record) {
                    // Last write wins.
                    $record->set('statusid', $statusid);
                    $record->update();
                }
            }

            if ($context) {
                attendance_updated::create([
                    'context' => $context,
                    'objectid' => $sessionid,
                    'relateduserid' => $userid,
                    'other' => ['statusid' => $statusid],
                ])->trigger();
            }
        }
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Counts the students with a recorded status in a session.
     *
     * @param int $sessionid
     * @return int
     */
    public static function count_for_session(int $sessionid): int {
        return attendance::count_records(['sessionid' => $sessionid]);
    }

    /**
     * Deletes the attendance of a session, without events.
     *
     * @param int $sessionid
     */
    public static function delete_for_session(int $sessionid): void {
        global $DB;
        $DB->delete_records(attendance::TABLE, ['sessionid' => $sessionid]);
    }

    /**
     * Deletes the attendance of every session of a roll, without events.
     *
     * @param int $rollcallid
     */
    public static function delete_for_rollcall(int $rollcallid): void {
        global $DB;
        $DB->delete_records_select(
            attendance::TABLE,
            'sessionid IN (SELECT id FROM {rollcall_session} WHERE rollcallid = :rollcallid)',
            ['rollcallid' => $rollcallid]
        );
    }

    /**
     * Deletes the attendance of every session of every roll in a course, without events.
     *
     * @param int $courseid
     */
    public static function delete_for_course(int $courseid): void {
        global $DB;
        $DB->delete_records_select(
            attendance::TABLE,
            'sessionid IN (SELECT s.id FROM {rollcall_session} s ' .
                'JOIN {rollcall} r ON r.id = s.rollcallid WHERE r.course = :courseid)',
            ['courseid' => $courseid]
        );
    }
}
