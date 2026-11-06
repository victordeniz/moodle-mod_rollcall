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

use mod_rollcall\local\session_manager;
use mod_rollcall\persistent\session;

/**
 * Tests for the sessions of a roll.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(session::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(session_manager::class)]
final class session_manager_test extends \advanced_testcase {
    /**
     * Creates a roll in a new course and returns its instance id.
     *
     * @return int
     */
    private function create_roll(): int {
        $course = $this->getDataGenerator()->create_course();
        return (int)$this->getDataGenerator()->create_module('rollcall', ['course' => $course->id])->id;
    }

    /**
     * Builds an unsaved session.
     *
     * @param int $rollcallid
     * @param string $name
     * @param int $duration
     * @return session
     */
    private function new_session(int $rollcallid, string $name, int $duration): session {
        return new session(0, (object)[
            'rollcallid' => $rollcallid,
            'groupid' => 0,
            'name' => $name,
            'sessdate' => time(),
            'duration' => $duration,
        ]);
    }

    public function test_session_field_rules(): void {
        $this->resetAfterTest();
        $rollid = $this->create_roll();

        $session = $this->new_session($rollid, 'Session 1', 3600)->create();
        $this->assertGreaterThan(0, $session->get('id'));

        foreach (['', '   '] as $name) {
            try {
                $this->new_session($rollid, $name, 3600)->create();
                $this->fail('Blank name accepted.');
            } catch (\core\invalid_persistent_exception $e) {
                $this->assertStringContainsString('name', $e->getMessage());
            }
        }

        foreach ([0, 59, 90, 86460] as $duration) {
            try {
                $this->new_session($rollid, 'Bad', $duration)->create();
                $this->fail("Duration $duration accepted.");
            } catch (\core\invalid_persistent_exception $e) {
                $this->assertStringContainsString('duration', $e->getMessage());
            }
        }

        $this->assertGreaterThan(0, $this->new_session($rollid, 'Min', 60)->create()->get('id'));
        $this->assertGreaterThan(0, $this->new_session($rollid, 'Max', 86400)->create()->get('id'));

        // Duplicate names are allowed within a roll.
        $this->assertGreaterThan(0, $this->new_session($rollid, 'Session 1', 3600)->create()->get('id'));
    }

    public function test_manager_crud_and_events(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $roll = $this->getDataGenerator()->create_module('rollcall', ['course' => $course->id]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $context = \context_module::instance($roll->cmid);

        // Without a context nothing is triggered, and the name is trimmed.
        $sink = $this->redirectEvents();
        $quiet = session_manager::create((int)$roll->id, 0, '  Quiet  ', 1000, 3600);
        $this->assertSame('Quiet', $quiet->get('name'));
        $this->assertCount(0, $sink->get_events());

        $session = session_manager::create((int)$roll->id, (int)$group->id, 'Lab', 2000, 3600, $context);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_rollcall\event\session_created::class, $events[0]);
        $this->assertEquals($group->id, $events[0]->other['groupid']);
        $this->assertEquals($session->get('id'), $events[0]->objectid);
        $sink->clear();

        $updated = session_manager::update((int)$session->get('id'), 0, ' Lecture ', 5000, 7200, $context);
        $this->assertSame('Lecture', $updated->get('name'));
        $reloaded = new session((int)$session->get('id'));
        $this->assertSame(0, (int)$reloaded->get('groupid'));
        $this->assertSame('Lecture', $reloaded->get('name'));
        $this->assertSame(5000, (int)$reloaded->get('sessdate'));
        $this->assertSame(7200, (int)$reloaded->get('duration'));
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_rollcall\event\session_updated::class, $events[0]);
        $sink->clear();

        $id = (int)$session->get('id');
        session_manager::delete($id, $context);
        $this->assertFalse(session::record_exists($id));
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_rollcall\event\session_deleted::class, $events[0]);
        $this->assertEquals($id, $events[0]->objectid);
        $this->assertSame('Lecture', $events[0]->other['name']);
        $this->assertStringContainsString("'$id'", $events[0]->get_description());
        $sink->close();

        $time = make_timestamp(2026, 11, 6, 10, 3);
        $name = session_manager::default_name($time);
        $this->assertStringContainsString('Session', $name);
        $this->assertStringContainsString(userdate($time, get_string('strftimedatefullshort', 'langconfig')), $name);
    }

    public function test_get_for_rollcall_rejects_session_of_other_activity(): void {
        $this->resetAfterTest();
        $first = $this->create_roll();
        $second = $this->create_roll();
        $session = session_manager::create($first, 0, 'Mine', 1000, 3600);

        $this->assertSame(
            (int)$session->get('id'),
            (int)session_manager::get_for_rollcall((int)$session->get('id'), $first)->get('id')
        );
        foreach ([[(int)$session->get('id'), $second], [999999, $first]] as [$sid, $rid]) {
            try {
                session_manager::get_for_rollcall($sid, $rid);
                $this->fail('Foreign or missing session accepted.');
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidsession', $e->errorcode);
            }
        }
    }
}
