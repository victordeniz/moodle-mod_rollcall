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
use mod_rollcall\local\classification;
use mod_rollcall\local\roster;

/**
 * Sets the category of one or more students of a roll.
 *
 * The whole batch is rejected, with no change, if any user is not listed in the roll or is outside the groups
 * the current user can access.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_category extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the roll'),
            'categoryid' => new external_value(PARAM_INT, 'Category id, or 0 to make the students regular'),
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User id'),
                'Students to classify'
            ),
        ]);
    }

    /**
     * Sets the category of the students.
     *
     * @param int $cmid
     * @param int $categoryid
     * @param int[] $userids
     * @return array List of ['userid' => int, 'categoryid' => int]
     */
    public static function execute(int $cmid, int $categoryid, array $userids): array {
        global $USER;

        [
            'cmid' => $cmid,
            'categoryid' => $categoryid,
            'userids' => $userids,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'categoryid' => $categoryid,
            'userids' => $userids,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'rollcall');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/rollcall:classify', $context);

        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (count(roster::filter_listed($cm, $userids, 0)) !== count($userids)) {
            throw new \moodle_exception('usernotlisted', 'mod_rollcall');
        }
        $allowedgroups = self::get_restricted_groups($cm, (int)$USER->id);
        if ($allowedgroups !== null && count(self::filter_group_members($userids, $allowedgroups)) !== count($userids)) {
            throw new \required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
        }

        $result = classification::set((int)$course->id, $userids, $categoryid, $context);
        $return = [];
        foreach ($result as $userid => $newcategoryid) {
            $return[] = ['userid' => (int)$userid, 'categoryid' => (int)$newcategoryid];
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
                'categoryid' => new external_value(PARAM_INT, 'Resulting category id, 0 for regular'),
            ])
        );
    }

    /**
     * Returns the ids of the groups the user is restricted to, or null when there is no group restriction.
     *
     * @param \cm_info $cm
     * @param int $userid
     * @return int[]|null
     */
    private static function get_restricted_groups(\cm_info $cm, int $userid): ?array {
        if (groups_get_activity_groupmode($cm) != SEPARATEGROUPS
                || has_capability('moodle/site:accessallgroups', $cm->context, $userid)) {
            return null;
        }
        return array_map('intval', array_keys(groups_get_all_groups($cm->course, $userid, $cm->groupingid, 'g.id')));
    }

    /**
     * Returns which of the users belong to at least one of the groups, in a single query.
     *
     * @param int[] $userids
     * @param int[] $groupids
     * @return int[]
     */
    private static function filter_group_members(array $userids, array $groupids): array {
        global $DB;

        if (empty($userids) || empty($groupids)) {
            return [];
        }
        [$usersql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'rollcalluser');
        [$groupsql, $groupparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'rollcallgroup');
        $sql = "SELECT DISTINCT userid
                  FROM {groups_members}
                 WHERE userid $usersql AND groupid $groupsql";
        return array_map('intval', $DB->get_fieldset_sql($sql, array_merge($params, $groupparams)));
    }
}
