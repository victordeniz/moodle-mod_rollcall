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

use mod_rollcall\event\category_created;
use mod_rollcall\event\category_deleted;
use mod_rollcall\event\category_updated;
use mod_rollcall\persistent\category;

/**
 * Manages the site-wide catalogue of student categories.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_manager {
    /**
     * Returns all categories, in display order.
     *
     * @return category[]
     */
    public static function get_all(): array {
        return category::get_records([], 'sortorder, id');
    }

    /**
     * Returns the visible categories, in display order.
     *
     * @return category[]
     */
    public static function get_visible(): array {
        return category::get_records(['visible' => 1], 'sortorder, id');
    }

    /**
     * Creates a category at the end of the catalogue.
     *
     * @param string $name
     * @param string $color Palette key
     * @return category
     */
    public static function create(string $name, string $color): category {
        global $DB;

        $max = $DB->get_field_sql('SELECT MAX(sortorder) FROM {' . category::TABLE . '}');
        $category = new category(0, (object)[
            'name' => $name,
            'color' => $color,
            'sortorder' => $max === null ? 0 : (int)$max + 1,
            'visible' => 1,
        ]);
        $category->create();
        self::trigger(category_created::class, $category);
        return $category;
    }

    /**
     * Changes the name and colour of a category.
     *
     * @param int $id
     * @param string $name
     * @param string $color Palette key
     * @return category
     */
    public static function update(int $id, string $name, string $color): category {
        $category = new category($id);
        $category->set('name', $name);
        $category->set('color', $color);
        $category->update();
        self::trigger(category_updated::class, $category);
        return $category;
    }

    /**
     * Shows or hides a category. Hidden categories keep their classifications.
     *
     * @param int $id
     * @param bool $visible
     */
    public static function set_visible(int $id, bool $visible): void {
        $category = new category($id);
        $category->set('visible', $visible);
        $category->update();
        self::trigger(category_updated::class, $category);
    }

    /**
     * Moves a category one position up (-1) or down (+1). Does nothing at the ends.
     *
     * @param int $id
     * @param int $direction -1 or 1
     */
    public static function move(int $id, int $direction): void {
        global $DB;

        $all = array_values(self::get_all());
        $ids = array_map(fn(category $c) => (int)$c->get('id'), $all);
        $from = array_search($id, $ids, true);
        if ($from === false) {
            throw new \moodle_exception('invalidcategory', 'mod_rollcall');
        }
        $to = $from + ($direction < 0 ? -1 : 1);
        if ($to < 0 || $to >= count($all)) {
            return;
        }

        // Renumber the whole list so duplicated sort orders cannot block the swap.
        [$all[$from], $all[$to]] = [$all[$to], $all[$from]];
        $transaction = $DB->start_delegated_transaction();
        foreach ($all as $position => $category) {
            if ((int)$category->get('sortorder') !== $position) {
                $category->set('sortorder', $position);
                $category->update();
            }
        }
        $transaction->allow_commit();
    }

    /**
     * Whether any student is classified in the category, in any course.
     *
     * @param int $id
     * @return bool
     */
    public static function is_in_use(int $id): bool {
        global $DB;
        return $DB->record_exists('rollcall_usercat', ['categoryid' => $id]);
    }

    /**
     * Deletes a category that no student has.
     *
     * @param int $id
     * @throws \moodle_exception categoryinuse if a student has the category
     */
    public static function delete(int $id): void {
        if (self::is_in_use($id)) {
            throw new \moodle_exception('categoryinuse', 'mod_rollcall');
        }
        $category = new category($id);
        $snapshot = $category->to_record();
        $category->delete();
        category_deleted::create([
            'context' => \context_system::instance(),
            'objectid' => $id,
            'other' => ['name' => $snapshot->name],
        ])->trigger();
    }

    /**
     * Triggers a catalogue event for the category.
     *
     * @param string $eventclass
     * @param category $category
     */
    private static function trigger(string $eventclass, category $category): void {
        $eventclass::create([
            'context' => \context_system::instance(),
            'objectid' => $category->get('id'),
            'other' => ['name' => $category->get('name')],
        ])->trigger();
    }
}
