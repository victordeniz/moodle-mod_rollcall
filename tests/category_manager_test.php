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

use mod_rollcall\event\category_created;
use mod_rollcall\event\category_deleted;
use mod_rollcall\local\category_manager;
use mod_rollcall\persistent\category;

/**
 * Tests for the category catalogue.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(category_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(category::class)]
final class category_manager_test extends \advanced_testcase {
    /**
     * Returns the names of the given categories, in order.
     *
     * @param category[] $categories
     * @return string[]
     */
    private function names(array $categories): array {
        return array_values(array_map(fn(category $c) => $c->get('name'), $categories));
    }

    /**
     * Removes the installed default categories so tests start from an empty catalogue.
     */
    private function empty_catalogue(): void {
        global $DB;
        $DB->delete_records('rollcall_category');
    }

    public function test_defaults_installed(): void {
        $this->resetAfterTest();

        $all = category_manager::get_all();
        $this->assertSame([
            get_string('defaultcat_repeater', 'mod_rollcall'),
            get_string('defaultcat_erasmus', 'mod_rollcall'),
        ], $this->names($all));
        $this->assertSame(['amber', 'blue'], array_values(array_map(fn(category $c) => $c->get('color'), $all)));
    }

    public function test_create_appends_sortorder_and_triggers_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $sink = $this->redirectEvents();
        $category = category_manager::create('Athlete', 'green');
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(2, (int)$category->get('sortorder'));
        $this->assertTrue((bool)$category->get('visible'));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(category_created::class, $events[0]);
        $this->assertEquals($category->get('id'), $events[0]->objectid);
    }

    public function test_create_rejects_unknown_color(): void {
        $this->resetAfterTest();

        $this->expectException(\core\invalid_persistent_exception::class);
        category_manager::create('X', 'fuchsia');
    }

    public function test_update(): void {
        $this->resetAfterTest();
        $this->empty_catalogue();
        $a = category_manager::create('A', 'red');

        category_manager::update($a->get('id'), 'A2', 'teal');

        $reloaded = new category($a->get('id'));
        $this->assertSame('A2', $reloaded->get('name'));
        $this->assertSame('teal', $reloaded->get('color'));
    }

    public function test_move(): void {
        $this->resetAfterTest();
        $this->empty_catalogue();
        $a = category_manager::create('A', 'red');
        category_manager::create('B', 'red');
        $c = category_manager::create('C', 'red');

        category_manager::move($c->get('id'), -1);
        $this->assertSame(['A', 'C', 'B'], $this->names(category_manager::get_all()));

        category_manager::move($a->get('id'), -1);
        $this->assertSame(['A', 'C', 'B'], $this->names(category_manager::get_all()));

        category_manager::move($a->get('id'), 1);
        $this->assertSame(['C', 'A', 'B'], $this->names(category_manager::get_all()));
    }

    public function test_set_visible_filters_get_visible(): void {
        $this->resetAfterTest();
        $this->empty_catalogue();
        category_manager::create('A', 'red');
        $b = category_manager::create('B', 'red');

        category_manager::set_visible($b->get('id'), false);

        $this->assertSame(['A'], $this->names(category_manager::get_visible()));
        $this->assertSame(['A', 'B'], $this->names(category_manager::get_all()));
    }

    public function test_delete_unused(): void {
        global $DB;
        $this->resetAfterTest();
        $category = category_manager::create('Unused', 'slate');
        $id = $category->get('id');

        $sink = $this->redirectEvents();
        category_manager::delete($id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse($DB->record_exists('rollcall_category', ['id' => $id]));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(category_deleted::class, $events[0]);
    }

    public function test_delete_in_use_throws(): void {
        global $DB;
        $this->resetAfterTest();
        $category = category_manager::create('Used', 'slate');
        $id = $category->get('id');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('rollcall_usercat', (object)[
            'courseid' => $course->id, 'userid' => $user->id, 'categoryid' => $id,
            'usermodified' => 0, 'timecreated' => 1, 'timemodified' => 1,
        ]);

        $this->assertTrue(category_manager::is_in_use($id));
        try {
            category_manager::delete($id);
            $this->fail('Deleting a category in use must throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('categoryinuse', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('rollcall_category', ['id' => $id]));
    }

    public function test_generator_creates_category(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->get_plugin_generator('mod_rollcall')->create_category(['color' => 'pink']);

        $this->assertSame('pink', $category->get('color'));
        $this->assertNotEmpty($category->get('name'));
    }
}
