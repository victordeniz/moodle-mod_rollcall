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

namespace mod_rollcall;

use mod_rollcall\event\attendance_updated;
use mod_rollcall\local\attendance_manager;
use mod_rollcall\local\session_manager;
use mod_rollcall\local\status_manager;

/**
 * Tests for the attendance manager.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attendance_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(attendance_updated::class)]
final class attendance_manager_test extends \advanced_testcase {
    /**
     * Builds a roll with a session, two statuses and three students.
     *
     * @return array [roll, session id, status A id, status B id, [user ids], context]
     */
    private function setup_roll(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $session = session_manager::create((int)$roll->id, 0, 'S', 1000, 3600);
        $statuses = array_values(status_manager::get_all((int)$roll->id));
        $users = [];
        for ($i = 0; $i < 3; $i++) {
            $users[] = (int)$gen->create_and_enrol($course, 'student')->id;
        }
        return [
            $roll,
            (int)$session->get('id'),
            (int)$statuses[0]->get('id'),
            (int)$statuses[1]->get('id'),
            $users,
            \context_module::instance($roll->cmid),
        ];
    }

    public function test_set_creates_updates_and_removes(): void {
        global $DB;
        $this->resetAfterTest();
        [, $s, $a, $b, [$u1, $u2, $u3]] = $this->setup_roll();

        $this->assertSame([$u1 => $a, $u2 => $a], attendance_manager::set($s, $a, [$u1, $u2]));
        $this->assertSame(2, $DB->count_records('rollcall_attendance', ['sessionid' => $s]));

        $DB->set_field('rollcall_attendance', 'timemodified', 5, ['sessionid' => $s, 'userid' => $u1]);
        $this->assertSame([$u1 => $b], attendance_manager::set($s, $b, [$u1]));
        $row = $DB->get_record('rollcall_attendance', ['sessionid' => $s, 'userid' => $u1]);
        $this->assertEquals($b, $row->statusid);
        $this->assertGreaterThan(5, (int)$row->timemodified);

        $this->assertSame([$u1 => 0, $u3 => 0], attendance_manager::set($s, 0, [$u1, $u3]));
        $this->assertSame(1, $DB->count_records('rollcall_attendance', ['sessionid' => $s]));
        $this->assertTrue($DB->record_exists('rollcall_attendance', ['sessionid' => $s, 'userid' => $u2]));
    }

    public function test_bulk_set_writes_all_rows(): void {
        $this->resetAfterTest();
        [$roll, $sessionid, $a, $b, $users] = $this->setup_roll();

        $result = attendance_manager::set($sessionid, $a, $users);
        $this->assertSame(array_fill_keys($users, $a), $result);
        $this->assertSame(array_fill_keys($users, $a), attendance_manager::get_for_users($sessionid, $users));
        $this->assertSame(3, attendance_manager::count_for_session($sessionid));

        attendance_manager::set($sessionid, $b, $users);
        $this->assertSame(array_fill_keys($users, $b), attendance_manager::get_for_users($sessionid, $users));
    }

    public function test_set_creates_the_row_after_it_was_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        [$roll, $sessionid, $a, $b, $users] = $this->setup_roll();
        attendance_manager::set($sessionid, $a, [$users[0]]);
        $DB->delete_records('rollcall_attendance', ['sessionid' => $sessionid]);
        $this->assertSame([$users[0] => $b], attendance_manager::set($sessionid, $b, [$users[0]]));
        $this->assertSame([$users[0] => $b], attendance_manager::get_for_users($sessionid, [$users[0]]));
    }

    public function test_set_triggers_one_event_per_changed_student(): void {
        $this->resetAfterTest();
        [, $s, $a, , [$u1, $u2], $ctx] = $this->setup_roll();

        $sink = $this->redirectEvents();
        attendance_manager::set($s, $a, [$u1, $u2], $ctx);
        $events = $sink->get_events();
        $this->assertCount(2, $events);
        foreach ($events as $i => $event) {
            $this->assertInstanceOf(attendance_updated::class, $event);
            $this->assertEquals([$u1, $u2][$i], $event->relateduserid);
            $this->assertEquals($s, $event->objectid);
            $this->assertEquals($a, $event->other['statusid']);
            $this->assertNotEmpty($event->get_url()->out());
        }

        $sink->clear();
        attendance_manager::set($s, $a, [$u1, $u2], $ctx);
        $this->assertCount(0, $sink->get_events());

        attendance_manager::set($s, 0, [$u1], $ctx);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertEquals(0, $events[0]->other['statusid']);
        $this->assertEquals($u1, $events[0]->relateduserid);
    }

    public function test_get_for_users_and_count(): void {
        $this->resetAfterTest();
        [, $s, $a, $b, [$u1, $u2, $u3]] = $this->setup_roll();
        attendance_manager::set($s, $a, [$u1]);
        attendance_manager::set($s, $b, [$u2]);

        $this->assertSame([$u1 => $a, $u2 => $b], attendance_manager::get_for_users($s, [$u1, $u2, $u3]));
        $this->assertSame(2, attendance_manager::count_for_session($s));
        $this->assertSame([], attendance_manager::get_for_users($s, []));
    }
}
