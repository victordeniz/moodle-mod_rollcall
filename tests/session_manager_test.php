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
}
