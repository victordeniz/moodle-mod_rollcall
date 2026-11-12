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

/**
 * Sends the attendance of the students to the gradebook.
 *
 * The grade of a student is the percentage of the attendance report over the maximum grade of the roll.
 * Nothing is sent automatically: the grades change only when this class is asked to send them.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grades {
    /**
     * Calculates the grades of the listed students and sends them to the gradebook.
     *
     * A student without past sessions gets no grade. Students who are no longer listed keep the grade they had.
     * The time of the update is stored only when every student is updated.
     *
     * @param \cm_info $cm The roll
     * @param int $userid Only this student, or 0 for every listed student
     * @param int|null $now Current time, for the past sessions and the time of the update
     * @return int Number of students processed; 0 when the roll has no grade
     */
    public static function sync(\cm_info $cm, int $userid = 0, ?int $now = null): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/rollcall/lib.php');

        $now ??= time();
        $rollcall = $DB->get_record('rollcall', ['id' => $cm->instance], '*', MUST_EXIST);
        if ((int)$rollcall->grade <= 0) {
            return 0;
        }

        $userids = array_map('intval', array_keys(roster::get_students($cm, 0)));
        if ($userid > 0) {
            $userids = array_values(array_intersect($userids, [$userid]));
        }

        $grades = [];
        foreach (report::summarise($cm, $userids, $now) as $id => $summary) {
            $grades[$id] = [
                'userid' => $id,
                'rawgrade' => $summary->percent === null ? null : (int)$rollcall->grade * $summary->percent / 100,
            ];
        }
        $rollcall->cmidnumber = $cm->idnumber;
        // Without students the item is still brought up to date.
        rollcall_grade_item_update($rollcall, $grades ?: null);

        if ($userid === 0) {
            $DB->set_field('rollcall', 'gradessynced', $now, ['id' => $rollcall->id]);
        }
        return count($userids);
    }
}
