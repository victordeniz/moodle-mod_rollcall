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

use mod_rollcall\event\status_created;
use mod_rollcall\event\status_deleted;
use mod_rollcall\event\status_updated;
use mod_rollcall\persistent\attendance;
use mod_rollcall\persistent\status;

/**
 * Manages the attendance statuses of a roll.
 *
 * Every method that changes a status triggers an event when it is given the module context.
 * Without a context (for example while the activity is being created, before its course
 * module exists) no event is triggered.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_manager {
    /**
     * Returns all the statuses of a roll, in display order.
     *
     * @param int $rollcallid
     * @return status[]
     */
    public static function get_all(int $rollcallid): array {
        return status::get_records(['rollcallid' => $rollcallid], 'sortorder, id');
    }

    /**
     * Returns the visible statuses of a roll, in display order.
     *
     * @param int $rollcallid
     * @return status[]
     */
    public static function get_visible(int $rollcallid): array {
        return status::get_records(['rollcallid' => $rollcallid, 'visible' => 1], 'sortorder, id');
    }

    /**
     * Returns a status, checking that it belongs to the roll.
     *
     * @param int $statusid
     * @param int $rollcallid
     * @return status
     * @throws \moodle_exception invalidstatus if it does not exist or belongs to another roll
     */
    public static function get_for_rollcall(int $statusid, int $rollcallid): status {
        $status = status::get_record(['id' => $statusid, 'rollcallid' => $rollcallid]);
        if (!$status) {
            throw new \moodle_exception('invalidstatus', 'mod_rollcall');
        }
        return $status;
    }

    /**
     * Creates a status at the end of the roll's list.
     *
     * @param int $rollcallid
     * @param string $name
     * @param \context_module|null $context
     * @return status
     */
    public static function create(int $rollcallid, string $name, ?\context_module $context = null): status {
        global $DB;

        $max = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {' . status::TABLE . '} WHERE rollcallid = :rollcallid',
            ['rollcallid' => $rollcallid]
        );
        $status = new status(0, (object)[
            'rollcallid' => $rollcallid,
            'name' => trim($name),
            'sortorder' => $max === null ? 0 : (int)$max + 1,
            'visible' => 1,
        ]);
        $status->create();
        self::trigger(status_created::class, $status, $context);
        return $status;
    }

    /**
     * Renames a status.
     *
     * @param int $id
     * @param string $name
     * @param \context_module|null $context
     * @return status
     */
    public static function update(int $id, string $name, ?\context_module $context = null): status {
        $status = new status($id);
        $status->set('name', trim($name));
        $status->update();
        self::trigger(status_updated::class, $status, $context);
        return $status;
    }

    /**
     * Shows or hides a status.
     *
     * @param int $id
     * @param bool $visible
     * @param \context_module|null $context
     */
    public static function set_visible(int $id, bool $visible, ?\context_module $context = null): void {
        $status = new status($id);
        $status->set('visible', $visible);
        $status->update();
        self::trigger(status_updated::class, $status, $context);
    }

    /**
     * Moves a status one position up (-1) or down (1). Does nothing at the ends.
     *
     * @param int $id
     * @param int $direction -1 or 1
     * @param \context_module|null $context
     */
    public static function move(int $id, int $direction, ?\context_module $context = null): void {
        global $DB;

        $status = new status($id);
        $all = array_values(self::get_all((int)$status->get('rollcallid')));
        $ids = array_map(fn(status $s) => (int)$s->get('id'), $all);
        $from = array_search($id, $ids, true);
        $to = $from + ($direction < 0 ? -1 : 1);
        if ($to < 0 || $to >= count($all)) {
            return;
        }

        // Renumber the whole list so duplicated sort orders cannot block the swap.
        [$all[$from], $all[$to]] = [$all[$to], $all[$from]];
        $transaction = $DB->start_delegated_transaction();
        foreach ($all as $position => $item) {
            if ((int)$item->get('sortorder') !== $position) {
                $item->set('sortorder', $position);
                $item->update();
            }
        }
        $transaction->allow_commit();
        self::trigger(status_updated::class, $status, $context);
    }

    /**
     * Whether the status has been used to record attendance.
     *
     * @param int $id
     * @return bool
     */
    public static function is_in_use(int $id): bool {
        global $DB;
        return $DB->record_exists(attendance::TABLE, ['statusid' => $id]);
    }

    /**
     * Deletes a status that is not in use.
     *
     * @param int $id
     * @param \context_module|null $context
     * @throws \moodle_exception statusinuse if the status has been used
     */
    public static function delete(int $id, ?\context_module $context = null): void {
        if (self::is_in_use($id)) {
            throw new \moodle_exception('statusinuse', 'mod_rollcall');
        }
        $status = new status($id);
        $snapshot = clone $status;
        $status->delete();
        // The persistent clears its id on delete, so the event gets the id and name from the snapshot.
        if ($context) {
            status_deleted::create([
                'context' => $context,
                'objectid' => $id,
                'other' => ['name' => $snapshot->get('name')],
            ])->trigger();
        }
    }

    /**
     * Deletes every status of a roll, without events.
     *
     * @param int $rollcallid
     */
    public static function delete_for_rollcall(int $rollcallid): void {
        global $DB;
        $DB->delete_records(status::TABLE, ['rollcallid' => $rollcallid]);
    }

    /**
     * Returns the names of the default statuses in a language.
     *
     * @param string $lang
     * @return string[]
     */
    public static function default_names(string $lang): array {
        $sm = get_string_manager();
        return [
            $sm->get_string('defaultstatus_present', 'mod_rollcall', null, $lang),
            $sm->get_string('defaultstatus_late', 'mod_rollcall', null, $lang),
            $sm->get_string('defaultstatus_absent', 'mod_rollcall', null, $lang),
        ];
    }

    /**
     * Returns the language of a course: the forced one, or the site language.
     *
     * @param int $courseid
     * @return string
     */
    public static function lang_for_course(int $courseid): string {
        global $CFG, $DB;
        $lang = $DB->get_field('course', 'lang', ['id' => $courseid]);
        return $lang ? (string)$lang : (string)$CFG->lang;
    }

    /**
     * Appends the default statuses that the roll does not have yet, matching by name.
     *
     * @param int $rollcallid
     * @param string $lang Language of the names
     * @param \context_module|null $context
     * @return int How many statuses were created
     */
    public static function add_missing_defaults(int $rollcallid, string $lang, ?\context_module $context = null): int {
        $existing = array_map(
            fn(status $s) => \core_text::strtolower(trim($s->get('name'))),
            self::get_all($rollcallid)
        );
        $created = 0;
        foreach (self::default_names($lang) as $name) {
            if (in_array(\core_text::strtolower(trim($name)), $existing, true)) {
                continue;
            }
            self::create($rollcallid, $name, $context);
            $created++;
        }
        return $created;
    }

    /**
     * Triggers a status event when a context is available.
     *
     * @param string $eventclass
     * @param status $status
     * @param \context_module|null $context
     */
    private static function trigger(string $eventclass, status $status, ?\context_module $context): void {
        if (!$context) {
            return;
        }
        $eventclass::create([
            'context' => $context,
            'objectid' => $status->get('id'),
            'other' => ['name' => $status->get('name')],
        ])->trigger();
    }
}
