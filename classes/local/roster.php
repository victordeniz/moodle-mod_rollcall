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
 * The students listed in a roll: active enrolment and the mod/rollcall:belisted capability.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roster {
    /** Capability that makes a user appear in the roll. */
    public const CAPABILITY = 'mod/rollcall:belisted';

    /**
     * Returns the listed students, sorted by last name and first name.
     *
     * @param \cm_info $cm
     * @param int $groupid Group to restrict to, or 0 for everyone
     * @return array<int, \stdClass> userid => user record with picture and name fields
     */
    public static function get_students(\cm_info $cm, int $groupid): array {
        global $DB;

        [$enrolledsql, $params] = get_enrolled_sql($cm->context, self::CAPABILITY, $groupid, true);
        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false);
        $sql = "SELECT {$fields->selects}
                  FROM {user} u
                  JOIN ($enrolledsql) je ON je.id = u.id
                       {$fields->joins}
              ORDER BY u.lastname, u.firstname, u.id";
        return $DB->get_records_sql($sql, array_merge($params, $fields->params));
    }

    /**
     * Whether the user is listed in the roll, optionally within a group.
     *
     * @param \cm_info $cm
     * @param int $userid
     * @param int $groupid Group the user must belong to, or 0 for any
     * @return bool
     */
    public static function is_listed(\cm_info $cm, int $userid, int $groupid): bool {
        return self::filter_listed($cm, [$userid], $groupid) === [$userid];
    }

    /**
     * Returns which of the users are listed in the roll, with the same rules as {@see get_students()}.
     *
     * Uses a single query whatever the number of users.
     *
     * @param \cm_info $cm
     * @param int[] $userids
     * @param int $groupid Group the users must belong to, or 0 for any
     * @return int[] The listed user ids, in ascending order
     */
    public static function filter_listed(\cm_info $cm, array $userids, int $groupid): array {
        global $DB;

        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (empty($userids)) {
            return [];
        }
        [$enrolledsql, $params] = get_enrolled_sql($cm->context, self::CAPABILITY, $groupid, true);
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'rollcalluser');
        $sql = "SELECT je.id
                  FROM ($enrolledsql) je
                 WHERE je.id $insql
              ORDER BY je.id";
        return array_map('intval', $DB->get_fieldset_sql($sql, array_merge($params, $inparams)));
    }
}
