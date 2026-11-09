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
namespace mod_rollcall\local;

/**
 * Calculates the attendance of students from the weights of their statuses.
 *
 * Nothing is stored: the summaries are calculated on every call with the current weights.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report {
    /**
     * Returns the attendance summary of each user over their past sessions.
     *
     * A session is past when it has started (its date is not after $now). The sessions of a user are those of the
     * whole activity, those of a group that no longer exists, and those of the groups the user belongs to now.
     * Each of them adds the weight of the user's status, or 0 when the user has none (unmarked). Records in other
     * sessions are ignored.
     *
     * @param \cm_info $cm The roll
     * @param int[] $userids
     * @param int $now Current time
     * @return array<int, student_summary> userid => summary, for every given user
     */
    public static function summarise(\cm_info $cm, array $userids, int $now): array {
        global $DB;

        $userids = array_values(array_unique(array_map('intval', $userids)));
        $empty = new student_summary(0, 0, [], null);
        if (!$userids) {
            return [];
        }
        $past = $DB->get_records_select_menu('rollcall_session', 'rollcallid = :rollcallid AND sessdate <= :now',
            ['rollcallid' => $cm->instance, 'now' => $now], 'id', 'id, groupid');
        if (!$past) {
            return array_fill_keys($userids, $empty);
        }

        // Sessions of a group that no longer exists belong to the whole activity.
        $existing = array_flip($DB->get_fieldset('groups', 'id', ['courseid' => $cm->course]));
        $effective = [];
        foreach ($past as $sessionid => $groupid) {
            $effective[(int)$sessionid] = isset($existing[(int)$groupid]) ? (int)$groupid : 0;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $memberships = [];
        $groupids = array_values(array_unique(array_filter($effective)));
        if ($groupids) {
            [$groupsql, $groupparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'g');
            $rs = $DB->get_recordset_select('groups_members', "groupid $groupsql AND userid $usersql",
                $groupparams + $userparams, '', 'id, groupid, userid');
            foreach ($rs as $member) {
                $memberships[(int)$member->userid][(int)$member->groupid] = true;
            }
            $rs->close();
        }

        [$sessionsql, $sessionparams] = $DB->get_in_or_equal(array_keys($effective), SQL_PARAMS_NAMED, 's');
        $records = [];
        $rs = $DB->get_recordset_sql(
            "SELECT a.id, a.sessionid, a.userid, a.statusid, st.weight
               FROM {rollcall_attendance} a
               JOIN {rollcall_status} st ON st.id = a.statusid
              WHERE a.sessionid $sessionsql AND a.userid $usersql",
            $sessionparams + $userparams
        );
        foreach ($rs as $record) {
            $records[(int)$record->userid][(int)$record->sessionid] = $record;
        }
        $rs->close();

        $summaries = [];
        foreach ($userids as $userid) {
            $total = 0;
            $unmarked = 0;
            $sum = 0;
            $counts = [];
            foreach ($effective as $sessionid => $groupid) {
                if ($groupid !== 0 && empty($memberships[$userid][$groupid])) {
                    continue;
                }
                $total++;
                $record = $records[$userid][$sessionid] ?? null;
                if ($record === null) {
                    $unmarked++;
                    continue;
                }
                $statusid = (int)$record->statusid;
                $counts[$statusid] = ($counts[$statusid] ?? 0) + 1;
                $sum += (int)$record->weight;
            }
            $summaries[$userid] = $total
                ? new student_summary($total, $unmarked, $counts, $sum / $total)
                : $empty;
        }
        return $summaries;
    }
}
