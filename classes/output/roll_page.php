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

namespace mod_rollcall\output;

use mod_rollcall\local\category_manager;
use mod_rollcall\local\classification;
use mod_rollcall\local\roster;

/**
 * The roll: students of the course with their category.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roll_page implements \renderable, \templatable {
    /** Filter value that shows every student. */
    public const FILTER_ALL = 'all';
    /** Filter value that shows students without a category. */
    public const FILTER_REGULAR = 'regular';

    /**
     * Constructor.
     *
     * @param \cm_info $cm The roll
     * @param int $groupid Group to show, 0 for everyone
     * @param string $filter 'all', 'regular' or a category id
     * @param bool $canclassify Whether the current user can change categories
     */
    public function __construct(
        /** @var \cm_info The roll */
        protected \cm_info $cm,
        /** @var int Group to show, 0 for everyone */
        protected int $groupid,
        /** @var string 'all', 'regular' or a category id */
        protected string $filter,
        /** @var bool Whether the current user can change categories */
        protected bool $canclassify,
    ) {
    }

    /**
     * Exports the data for the mod_rollcall/roll template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $systemcontext = \context_system::instance();
        $regular = ['id' => 0, 'name' => get_string('regular', 'mod_rollcall'), 'color' => ''];

        $categories = [];
        foreach (category_manager::get_visible() as $category) {
            $id = (int)$category->get('id');
            $categories[$id] = [
                'id' => $id,
                'name' => format_string($category->get('name'), true, ['context' => $systemcontext]),
                'color' => $category->get('color'),
            ];
        }

        $filter = $this->filter;
        if ($filter !== self::FILTER_REGULAR && !isset($categories[(int)$filter])) {
            $filter = self::FILTER_ALL;
        }

        $students = roster::get_students($this->cm, $this->groupid);
        $effective = classification::get_effective((int)$this->cm->course, array_keys($students));

        $counts = array_fill_keys(array_merge(array_keys($categories), [0]), 0);
        $rows = [];
        foreach ($students as $userid => $user) {
            $categoryid = isset($effective[$userid]) ? (int)$effective[$userid]->get('id') : 0;
            $counts[$categoryid]++;
            if (!$this->matches($filter, $categoryid)) {
                continue;
            }
            $current = $categories[$categoryid] ?? $regular;
            $row = [
                'userid' => (int)$userid,
                'fullname' => fullname($user),
                'picture' => $output->user_picture($user, ['courseid' => $this->cm->course, 'size' => 35]),
                'categoryid' => $categoryid,
                'categoryname' => $current['name'],
                'color' => $current['color'],
                'hascategory' => $categoryid !== 0,
            ];
            if ($this->canclassify) {
                $row['options'] = [];
                foreach (array_merge([$regular], array_values($categories)) as $option) {
                    $row['options'][] = $option + ['selected' => $option['id'] === $categoryid];
                }
            }
            $rows[] = $row;
        }

        $legend = [];
        foreach (array_merge(array_values($categories), [$regular]) as $item) {
            $legend[] = $item + ['count' => $counts[$item['id']]];
        }

        $filters = [[
            'value' => self::FILTER_ALL,
            'name' => get_string('filterall', 'mod_rollcall'),
            'selected' => $filter === self::FILTER_ALL,
        ], [
            'value' => self::FILTER_REGULAR,
            'name' => $regular['name'],
            'selected' => $filter === self::FILTER_REGULAR,
        ]];
        foreach ($categories as $id => $category) {
            $filters[] = ['value' => (string)$id, 'name' => $category['name'], 'selected' => $filter === (string)$id];
        }

        return [
            'cmid' => (int)$this->cm->id,
            'groupid' => $this->groupid,
            'viewurl' => (new \moodle_url('/mod/rollcall/students.php'))->out(false),
            'canclassify' => $this->canclassify,
            'legend' => $legend,
            'categories' => array_merge([$regular], array_values($categories)),
            'filters' => $filters,
            'rows' => $rows,
            'empty' => empty($rows),
        ];
    }

    /**
     * Whether a student with the category passes the filter.
     *
     * @param string $filter Validated filter
     * @param int $categoryid Effective category, 0 for regular
     * @return bool
     */
    private function matches(string $filter, int $categoryid): bool {
        return match ($filter) {
            self::FILTER_ALL => true,
            self::FILTER_REGULAR => $categoryid === 0,
            default => $categoryid === (int)$filter,
        };
    }
}
