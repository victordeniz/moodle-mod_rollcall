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

namespace mod_rollcall\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_rollcall.
 *
 * Classifications belong to the course, so their data lives in the course context. The category catalogue only
 * stores who last modified each category, in the system context.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describes the personal data stored by the plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('rollcall_usercat', [
            'userid' => 'privacy:metadata:rollcall_usercat:userid',
            'categoryid' => 'privacy:metadata:rollcall_usercat:categoryid',
            'usermodified' => 'privacy:metadata:rollcall_usercat:usermodified',
            'timemodified' => 'privacy:metadata:rollcall_usercat:timemodified',
        ], 'privacy:metadata:rollcall_usercat');
        $collection->add_database_table('rollcall_category', [
            'usermodified' => 'privacy:metadata:rollcall_category:usermodified',
            'timemodified' => 'privacy:metadata:rollcall_category:timemodified',
        ], 'privacy:metadata:rollcall_category');
        return $collection;
    }

    /**
     * Returns the contexts with data about the user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {context} ctx
              JOIN {rollcall_usercat} uc ON uc.courseid = ctx.instanceid AND ctx.contextlevel = :courselevel
             WHERE uc.userid = :userid OR uc.usermodified = :modifier", [
            'courselevel' => CONTEXT_COURSE,
            'userid' => $userid,
            'modifier' => $userid,
        ]);
        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {context} ctx
             WHERE ctx.contextlevel = :systemlevel
               AND EXISTS (SELECT 1 FROM {rollcall_category} c WHERE c.usermodified = :userid)", [
            'systemlevel' => CONTEXT_SYSTEM,
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Adds the users with data in the context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_course) {
            $params = ['courseid' => $context->instanceid];
            $userlist->add_from_sql('userid', 'SELECT userid FROM {rollcall_usercat} WHERE courseid = :courseid', $params);
            $userlist->add_from_sql('usermodified',
                'SELECT usermodified FROM {rollcall_usercat} WHERE courseid = :courseid AND usermodified <> 0', $params);
        } else if ($context instanceof \context_system) {
            $userlist->add_from_sql('usermodified',
                'SELECT usermodified FROM {rollcall_category} WHERE usermodified <> 0', []);
        }
    }

    /**
     * Exports the user's data.
     *
     * The student gets their own category. The teacher who classified gets the classifications they made,
     * without identifying the students.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        $path = [get_string('privacy:path', 'mod_rollcall')];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $sql = "SELECT uc.userid, uc.usermodified, uc.timemodified, c.name
                          FROM {rollcall_usercat} uc
                          JOIN {rollcall_category} c ON c.id = uc.categoryid
                         WHERE uc.courseid = :courseid AND (uc.userid = :userid OR uc.usermodified = :modifier)";
                $rows = $DB->get_recordset_sql($sql,
                    ['courseid' => $context->instanceid, 'userid' => $userid, 'modifier' => $userid]);
                $data = (object)['category' => null, 'timemodified' => null, 'classificationsmade' => []];
                foreach ($rows as $row) {
                    if ((int)$row->userid === $userid) {
                        $data->category = format_string($row->name, true, ['context' => $context]);
                        $data->timemodified = transform::datetime($row->timemodified);
                    }
                    if ((int)$row->usermodified === $userid) {
                        $data->classificationsmade[] = (object)[
                            'category' => format_string($row->name, true, ['context' => $context]),
                            'timemodified' => transform::datetime($row->timemodified),
                        ];
                    }
                }
                $rows->close();
                writer::with_context($context)->export_data($path, $data);
            } else if ($context instanceof \context_system) {
                $names = $DB->get_fieldset_select('rollcall_category', 'name', 'usermodified = ?', [$userid]);
                writer::with_context($context)->export_data($path, (object)['categoriesmodified' => $names]);
            }
        }
    }

    /**
     * Deletes all data in the context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_course) {
            \mod_rollcall\local\classification::delete_for_course((int)$context->instanceid);
        } else if ($context instanceof \context_system) {
            $DB->set_field('rollcall_category', 'usermodified', 0);
        }
    }

    /**
     * Deletes the user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users_in_context($context, [$userid]);
        }
    }

    /**
     * Deletes the data of several users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_users_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Deletes the classifications of the users and anonymises what they modified.
     *
     * @param \context $context
     * @param int[] $userids
     */
    private static function delete_users_in_context(\context $context, array $userids): void {
        global $DB;
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        if ($context instanceof \context_course) {
            $params['courseid'] = $context->instanceid;
            $DB->delete_records_select('rollcall_usercat', "courseid = :courseid AND userid $insql", $params);
            $DB->set_field_select('rollcall_usercat', 'usermodified', 0, "courseid = :courseid AND usermodified $insql",
                $params);
        } else if ($context instanceof \context_system) {
            $DB->set_field_select('rollcall_category', 'usermodified', 0, "usermodified $insql", $params);
        }
    }
}
