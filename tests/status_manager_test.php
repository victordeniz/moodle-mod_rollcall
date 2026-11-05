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

use mod_rollcall\persistent\status;

/**
 * Tests for the attendance statuses.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(status::class)]
final class status_manager_test extends \advanced_testcase {
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
     * Builds an unsaved status.
     *
     * @param int $rollcallid
     * @param string $name
     * @return status
     */
    private function new_status(int $rollcallid, string $name): status {
        return new status(0, (object)['rollcallid' => $rollcallid, 'name' => $name, 'sortorder' => 0, 'visible' => 1]);
    }

    public function test_status_name_rules(): void {
        $this->resetAfterTest();
        $first = $this->create_roll();
        $second = $this->create_roll();

        $this->new_status($first, 'Excused')->create();

        // Same name, other case and padding, in the same activity: rejected.
        try {
            $this->new_status($first, '  excused ')->create();
            $this->fail('A duplicate name was accepted.');
        } catch (\core\invalid_persistent_exception $e) {
            $this->assertStringContainsString('name', $e->getMessage());
        }

        // Empty and blank names: rejected.
        foreach (['', '   '] as $blank) {
            try {
                $this->new_status($first, $blank)->create();
                $this->fail('A blank name was accepted.');
            } catch (\core\invalid_persistent_exception $e) {
                $this->assertTrue(true);
            }
        }

        // The same name in another activity: allowed.
        $other = $this->new_status($second, 'Excused')->create();
        $this->assertGreaterThan(0, (int)$other->get('id'));
    }
}
