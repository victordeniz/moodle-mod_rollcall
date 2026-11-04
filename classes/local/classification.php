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

use mod_rollcall\event\user_category_updated;
use mod_rollcall\persistent\category;
use mod_rollcall\persistent\user_category;

/**
 * Per-course classification of students.
 *
 * This is the single place that decides which category a student effectively has. Version 1 only uses
 * manual classifications restricted to visible categories; cohort-based categories will be added here.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class classification {
    /**
     * Returns the effective category of each user in the course.
     *
     * @param int $courseid
     * @param int[] $userids
     * @return array<int, category|null> userid => category, or null when the student is regular
     */
    public static function get_effective(int $courseid, array $userids): array {
        if (empty($userids)) {
            return [];
        }

        $visible = [];
        foreach (category_manager::get_visible() as $category) {
            $visible[(int)$category->get('id')] = $category;
        }

        $result = [];
        $assigned = self::get_assigned($courseid, $userids);
        foreach ($userids as $userid) {
            $categoryid = $assigned[(int)$userid] ?? 0;
            $result[(int)$userid] = $visible[$categoryid] ?? null;
        }
        return $result;
    }

    /**
     * Sets the category of several users in a course. Does not check permissions.
     *
     * @param int $courseid
     * @param int[] $userids
     * @param int $categoryid A visible category, or 0 to make the users regular
     * @param \context_module $context Context of the activity used to classify, for the events
     * @return array<int, int> userid => resulting category id (0 for regular)
     * @throws \moodle_exception invalidcategory if the category does not exist or is hidden
     */
    public static function set(int $courseid, array $userids, int $categoryid, \context_module $context): array {
        global $DB;

        if ($categoryid !== 0 && !category::record_exists_select('id = :id AND visible = 1', ['id' => $categoryid])) {
            throw new \moodle_exception('invalidcategory', 'mod_rollcall');
        }

        $before = self::get_effective($courseid, $userids);
        $records = [];
        if ($userids) {
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $params['courseid'] = $courseid;
            foreach (user_category::get_records_select("courseid = :courseid AND userid $insql", $params) as $record) {
                $records[(int)$record->get('userid')] = $record;
            }
        }

        $result = [];
        $transaction = $DB->start_delegated_transaction();
        foreach ($userids as $userid) {
            $userid = (int)$userid;
            $record = $records[$userid] ?? null;
            if ($categoryid === 0) {
                $record?->delete();
            } else if ($record === null) {
                (new user_category(0, (object)[
                    'courseid' => $courseid,
                    'userid' => $userid,
                    'categoryid' => $categoryid,
                ]))->create();
            } else if ((int)$record->get('categoryid') !== $categoryid) {
                $record->set('categoryid', $categoryid);
                $record->update();
            }
            $result[$userid] = $categoryid;
        }
        $transaction->allow_commit();

        foreach ($result as $userid => $newid) {
            $oldid = isset($before[$userid]) ? (int)$before[$userid]->get('id') : 0;
            if ($oldid !== $newid) {
                user_category_updated::create([
                    'context' => $context,
                    'relateduserid' => $userid,
                    'other' => ['oldcategoryid' => $oldid, 'newcategoryid' => $newid],
                ])->trigger();
            }
        }
        return $result;
    }

    /**
     * Deletes every classification in a course.
     *
     * @param int $courseid
     */
    public static function delete_for_course(int $courseid): void {
        global $DB;
        $DB->delete_records(user_category::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Returns the stored category id of each classified user, including hidden categories.
     *
     * @param int $courseid
     * @param int[] $userids
     * @return array<int, int> userid => categoryid
     */
    private static function get_assigned(int $courseid, array $userids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;
        $rows = $DB->get_records_select_menu(user_category::TABLE, "courseid = :courseid AND userid $insql",
            $params, '', 'userid, categoryid');
        return array_map('intval', $rows);
    }
}
