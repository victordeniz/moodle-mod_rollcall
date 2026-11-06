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

use mod_rollcall\event\session_created;
use mod_rollcall\event\session_deleted;
use mod_rollcall\event\session_updated;
use mod_rollcall\persistent\session;

/**
 * Manages the sessions of a roll.
 *
 * Every method that changes a session triggers an event when it is given the module context.
 * Without a context no event is triggered.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_manager {
    /**
     * Returns a session, checking that it belongs to the roll.
     *
     * @param int $sessionid
     * @param int $rollcallid
     * @return session
     * @throws \moodle_exception invalidsession if it does not exist or belongs to another roll
     */
    public static function get_for_rollcall(int $sessionid, int $rollcallid): session {
        $session = session::get_record(['id' => $sessionid, 'rollcallid' => $rollcallid]);
        if (!$session) {
            throw new \moodle_exception('invalidsession', 'mod_rollcall');
        }
        return $session;
    }

    /**
     * Creates a session.
     *
     * @param int $rollcallid
     * @param int $groupid 0 for the whole activity
     * @param string $name
     * @param int $sessdate
     * @param int $duration In seconds
     * @param \context_module|null $context
     * @return session
     */
    public static function create(
        int $rollcallid,
        int $groupid,
        string $name,
        int $sessdate,
        int $duration,
        ?\context_module $context = null
    ): session {
        $session = new session(0, (object)[
            'rollcallid' => $rollcallid,
            'groupid' => $groupid,
            'name' => trim($name),
            'sessdate' => $sessdate,
            'duration' => $duration,
        ]);
        $session->create();
        self::trigger(session_created::class, $session, $context);
        return $session;
    }

    /**
     * Updates a session.
     *
     * @param int $id
     * @param int $groupid 0 for the whole activity
     * @param string $name
     * @param int $sessdate
     * @param int $duration In seconds
     * @param \context_module|null $context
     * @return session
     */
    public static function update(
        int $id,
        int $groupid,
        string $name,
        int $sessdate,
        int $duration,
        ?\context_module $context = null
    ): session {
        $session = new session($id);
        $session->set('groupid', $groupid);
        $session->set('name', trim($name));
        $session->set('sessdate', $sessdate);
        $session->set('duration', $duration);
        $session->update();
        self::trigger(session_updated::class, $session, $context);
        return $session;
    }

    /**
     * Deletes a session.
     *
     * @param int $id
     * @param \context_module|null $context
     */
    public static function delete(int $id, ?\context_module $context = null): void {
        $session = new session($id);
        $snapshot = clone $session;
        $session->delete();
        // The persistent clears its id on delete, so the event gets the data from the snapshot.
        if ($context) {
            session_deleted::create([
                'context' => $context,
                'objectid' => $id,
                'other' => [
                    'name' => $snapshot->get('name'),
                    'groupid' => (int)$snapshot->get('groupid'),
                ],
            ])->trigger();
        }
    }

    /**
     * Deletes every session of a roll, without events.
     *
     * @param int $rollcallid
     */
    public static function delete_for_rollcall(int $rollcallid): void {
        global $DB;
        $DB->delete_records(session::TABLE, ['rollcallid' => $rollcallid]);
    }

    /**
     * Deletes the sessions of every roll in a course, without events.
     *
     * @param int $courseid
     */
    public static function delete_for_course(int $courseid): void {
        global $DB;
        $DB->delete_records_select(
            session::TABLE,
            'rollcallid IN (SELECT id FROM {rollcall} WHERE course = :courseid)',
            ['courseid' => $courseid]
        );
    }

    /**
     * Returns the default name of a session starting at a given time.
     *
     * @param int $time
     * @return string
     */
    public static function default_name(int $time): string {
        return get_string(
            'defaultsessionname',
            'mod_rollcall',
            userdate($time, get_string('strftimedatefullshort', 'langconfig'))
        );
    }

    /**
     * Triggers a session event when a context is available.
     *
     * @param string $eventclass
     * @param session $session
     * @param \context_module|null $context
     */
    private static function trigger(string $eventclass, session $session, ?\context_module $context): void {
        if (!$context) {
            return;
        }
        $eventclass::create([
            'context' => $context,
            'objectid' => $session->get('id'),
            'other' => [
                'name' => $session->get('name'),
                'groupid' => (int)$session->get('groupid'),
            ],
        ])->trigger();
    }
}
