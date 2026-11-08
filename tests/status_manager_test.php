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

use mod_rollcall\event\status_created;
use mod_rollcall\event\status_deleted;
use mod_rollcall\event\status_updated;
use mod_rollcall\local\status_manager;
use mod_rollcall\persistent\status;

/**
 * Tests for the attendance statuses.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(status_manager::class)]
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

    /**
     * Creates a roll with no statuses and returns its instance id and module context.
     *
     * @return array [int instance id, \context_module]
     */
    private function create_empty_roll(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $roll = $this->getDataGenerator()->create_module('rollcall', ['course' => $course->id]);
        $DB->delete_records('rollcall_status', ['rollcallid' => $roll->id]);
        return [(int)$roll->id, \context_module::instance($roll->cmid)];
    }

    /**
     * Returns the names of the statuses of a roll, in order.
     *
     * @param int $rollcallid
     * @return string[]
     */
    private function names(int $rollcallid): array {
        return array_values(array_map(fn(status $s) => $s->get('name'), status_manager::get_all($rollcallid)));
    }

    public function test_manager_crud_order_and_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$id, $context] = $this->create_empty_roll();

        $sink = $this->redirectEvents();
        $a = status_manager::create($id, 'A', $context);
        $b = status_manager::create($id, 'B', $context);
        $c = status_manager::create($id, 'C');
        $events = $sink->get_events();
        $this->assertCount(2, $events, 'Creating without a context triggers no event.');
        $this->assertInstanceOf(status_created::class, $events[0]);
        $this->assertEquals($a->get('id'), $events[0]->objectid);
        $this->assertSame([0, 1, 2], array_values(array_map(fn(status $s) => (int)$s->get('sortorder'),
            status_manager::get_all($id))));

        status_manager::update((int)$a->get('id'), 'A2', $context);
        $this->assertInstanceOf(status_updated::class, $sink->get_events()[2]);
        $this->assertSame(['A2', 'B', 'C'], $this->names($id));

        status_manager::move((int)$c->get('id'), -1, $context);
        $this->assertSame(['A2', 'C', 'B'], $this->names($id));
        status_manager::move((int)$a->get('id'), -1, $context);
        status_manager::move((int)$b->get('id'), 1, $context);
        $this->assertSame(['A2', 'C', 'B'], $this->names($id), 'Moving past the ends does nothing.');

        status_manager::set_visible((int)$b->get('id'), false, $context);
        $this->assertSame(['A2', 'C'], array_values(array_map(fn(status $s) => $s->get('name'),
            status_manager::get_visible($id))));
        $this->assertSame(['A2', 'C', 'B'], $this->names($id), 'Hidden statuses stay in the full list.');

        status_manager::delete((int)$b->get('id'), $context);
        $deleted = array_filter($sink->get_events(), fn($e) => $e instanceof status_deleted);
        $sink->close();
        $this->assertCount(1, $deleted);
        $this->assertSame(['A2', 'C'], $this->names($id));
    }

    public function test_add_missing_defaults(): void {
        $this->resetAfterTest();
        [$id] = $this->create_empty_roll();

        $this->assertSame(3, status_manager::add_missing_defaults($id, 'en'));
        $this->assertSame(status_manager::default_names('en'), $this->names($id));

        $first = array_values(status_manager::get_all($id))[0];
        status_manager::update((int)$first->get('id'), 'Tarde');
        $this->assertSame(1, status_manager::add_missing_defaults($id, 'en'));
        $this->assertSame(0, status_manager::add_missing_defaults($id, 'en'));
        $names = $this->names($id);
        $this->assertContains('Tarde', $names);
        $this->assertContains(status_manager::default_names('en')[0], $names);
        $this->assertCount(4, $names);
    }

    public function test_statuses_are_scoped_per_activity(): void {
        $this->resetAfterTest();
        [$first] = $this->create_empty_roll();
        [$second] = $this->create_empty_roll();

        status_manager::create($first, 'Only here');
        $this->assertSame(['Only here'], $this->names($first));
        $this->assertSame([], $this->names($second));
        $this->assertSame(0, (int)status_manager::create($second, 'Mine')->get('sortorder'));
    }

    public function test_status_in_use_cannot_be_deleted(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $session = \mod_rollcall\local\session_manager::create((int)$roll->id, 0, 'S', 1000, 3600);
        $user = $gen->create_and_enrol($course, 'student');
        $statusid = (int)array_values(status_manager::get_all((int)$roll->id))[0]->get('id');

        $this->assertFalse(status_manager::is_in_use($statusid));
        \mod_rollcall\local\attendance_manager::set((int)$session->get('id'), $statusid, [(int)$user->id]);
        $this->assertTrue(status_manager::is_in_use($statusid));
        try {
            status_manager::delete($statusid);
            $this->fail('A status in use was deleted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('statusinuse', $e->errorcode);
        }
    }
}
