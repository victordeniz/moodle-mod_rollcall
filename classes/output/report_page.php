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
use mod_rollcall\local\report;
use mod_rollcall\local\roster;
use mod_rollcall\local\status_manager;
use mod_rollcall\local\student_summary;

/**
 * Attendance report: per student, the count of each status over the past sessions and the attendance percentage.
 *
 * Filterable by category, sortable by name or percentage and paged. Read only.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_page implements \renderable, \templatable {
    /** Sort by last name. */
    public const SORT_LASTNAME = 'lastname';
    /** Sort by first name. */
    public const SORT_FIRSTNAME = 'firstname';
    /** Sort by attendance percentage. */
    public const SORT_PERCENT = 'percent';
    /** Shown instead of the percentage when a student has no past sessions. */
    private const NO_PERCENT = '—';

    /**
     * Constructor.
     *
     * @param \cm_info $cm The roll
     * @param int $groupid Group to list, or 0 for everyone; the caller has checked the user can see it
     * @param string $filter 'all', 'regular' or a category id
     * @param string $sort 'lastname', 'firstname' or 'percent'
     * @param string $dir 'asc' or 'desc'
     * @param int $page Zero-based page, clamped to the existing ones
     * @param int $perpage Students per page
     * @param int $now Current time; sessions that start later are not counted
     */
    public function __construct(
        /** @var \cm_info The roll */
        protected \cm_info $cm,
        /** @var int Group to list, or 0 */
        protected int $groupid,
        /** @var string 'all', 'regular' or a category id */
        protected string $filter,
        /** @var string 'lastname', 'firstname' or 'percent' */
        protected string $sort,
        /** @var string 'asc' or 'desc' */
        protected string $dir,
        /** @var int Zero-based page */
        protected int $page,
        /** @var int Students per page */
        protected int $perpage,
        /** @var int Current time */
        protected int $now,
    ) {
    }

    /**
     * Exports the data for the mod_rollcall/report_page template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $DB;

        $cm = $this->cm;
        $context = $cm->context;
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
        if ($filter !== roll_page::FILTER_REGULAR && !isset($categories[(int)$filter])) {
            $filter = roll_page::FILTER_ALL;
        }
        $sort = in_array($this->sort, [self::SORT_FIRSTNAME, self::SORT_PERCENT], true) ? $this->sort : self::SORT_LASTNAME;
        $dir = $this->dir === 'desc' ? 'desc' : 'asc';

        $students = roster::get_students($cm, $this->groupid);
        $effective = classification::get_effective((int)$cm->course, array_keys($students));
        $matching = [];
        foreach ($students as $userid => $user) {
            $categoryid = isset($effective[$userid]) ? (int)$effective[$userid]->get('id') : 0;
            if ($this->matches($filter, $categoryid)) {
                $user->categoryid = $categoryid;
                $matching[$userid] = $user;
            }
        }
        // Every matching student is summarised before paging, so the percentage can be sorted and the columns
        // do not change from one page to another.
        $summaries = report::summarise($cm, array_keys($matching), $this->now);
        $matching = $this->sorted($matching, $summaries, $sort, $dir);

        $total = count($matching);
        $lastpage = $total > 0 ? (int)ceil($total / $this->perpage) - 1 : 0;
        $page = max(0, min($this->page, $lastpage));
        $pagestudents = array_slice($matching, $page * $this->perpage, $this->perpage, true);

        // Visible statuses, plus the hidden ones that a listed student has in a past session.
        $used = [];
        foreach ($summaries as $summary) {
            $used += $summary->counts;
        }
        $columns = [];
        $columnids = [];
        foreach (status_manager::get_all((int)$cm->instance) as $status) {
            $id = (int)$status->get('id');
            $hidden = !$status->get('visible');
            if ($hidden && !isset($used[$id])) {
                continue;
            }
            $name = get_string('statusweight', 'mod_rollcall', (object)[
                'name' => format_string($status->get('name'), true, ['context' => $context]),
                'weight' => (int)$status->get('weight'),
            ]);
            $columns[] = [
                'name' => $hidden ? get_string('statushidden', 'mod_rollcall', $name) : $name,
                'hidden' => $hidden,
            ];
            $columnids[] = $id;
        }

        $rows = [];
        foreach ($pagestudents as $userid => $user) {
            $summary = $summaries[$userid];
            $display = $categories[$user->categoryid] ?? $regular;
            $rows[] = [
                'userid' => (int)$userid,
                'fullname' => fullname($user),
                'picture' => $output->user_picture($user, ['courseid' => $cm->course, 'size' => 35]),
                'categoryname' => $display['name'],
                'color' => $display['color'],
                'hascategory' => $user->categoryid !== 0,
                'counts' => array_map(fn(int $id) => ['value' => $summary->counts[$id] ?? 0], $columnids),
                'unmarked' => $summary->unmarked,
                'haswarning' => $summary->unmarked > 0,
                'percent' => $summary->percent === null ? self::NO_PERCENT
                    : get_string('percentvalue', 'mod_rollcall', format_float($summary->percent, 1)),
            ];
        }

        $filters = [[
            'value' => roll_page::FILTER_ALL,
            'name' => get_string('filterall', 'mod_rollcall'),
            'selected' => $filter === roll_page::FILTER_ALL,
        ], [
            'value' => roll_page::FILTER_REGULAR,
            'name' => $regular['name'],
            'selected' => $filter === roll_page::FILTER_REGULAR,
        ]];
        foreach ($categories as $id => $category) {
            $filters[] = ['value' => (string)$id, 'name' => $category['name'], 'selected' => $filter === (string)$id];
        }

        $params = ['id' => $cm->id, 'filter' => $filter, 'sort' => $sort, 'dir' => $dir];
        if ($this->groupid) {
            $params['group'] = $this->groupid;
        }
        $baseurl = new \moodle_url('/mod/rollcall/report.php', $params);
        $sortlinks = [];
        $labels = [
            self::SORT_FIRSTNAME => get_string('firstname'),
            self::SORT_LASTNAME => get_string('lastname'),
            self::SORT_PERCENT => get_string('attendancepercent', 'mod_rollcall'),
        ];
        foreach ($labels as $key => $label) {
            $active = $key === $sort;
            $sortlinks[] = [
                'key' => $key,
                'name' => $label,
                'url' => (new \moodle_url($baseurl, ['sort' => $key, 'dir' => $active && $dir === 'asc' ? 'desc' : 'asc']))->out(false),
                'active' => $active,
                'dir' => $active ? $dir : '',
                'arrow' => $active ? ($dir === 'asc' ? '▲' : '▼') : '',
            ];
        }

        return [
            'cmid' => (int)$cm->id,
            'groupid' => $this->groupid,
            'sort' => $sort,
            'dir' => $dir,
            'reporturl' => (new \moodle_url('/mod/rollcall/report.php'))->out(false),
            'filters' => $filters,
            'sortlinks' => $sortlinks,
            'columns' => $columns,
            'rows' => $rows,
            'pagingbar' => $output->paging_bar($total, $page, $this->perpage, $baseurl),
            'nopastsessions' => !$DB->record_exists_select('rollcall_session', 'rollcallid = :rollcallid AND sessdate <= :now',
                ['rollcallid' => $cm->instance, 'now' => $this->now]),
            'empty' => empty($rows),
        ];
    }

    /**
     * Sorts the students by name or by percentage.
     *
     * Students without past sessions go last in both directions; ties keep the order by last name.
     *
     * @param \stdClass[] $students userid => user
     * @param student_summary[] $summaries userid => summary
     * @param string $sort Validated sort
     * @param string $dir 'asc' or 'desc'
     * @return \stdClass[]
     */
    private function sorted(array $students, array $summaries, string $sort, string $dir): array {
        // Locale-aware sort on the chosen name.
        \core_collator::asort_objects_by_property($students, $sort === self::SORT_FIRSTNAME ? 'firstname' : 'lastname');
        if ($sort !== self::SORT_PERCENT) {
            return $dir === 'desc' ? array_reverse($students, true) : $students;
        }
        $position = array_flip(array_keys($students));
        uksort($students, function(int $a, int $b) use ($summaries, $position, $dir): int {
            $pa = $summaries[$a]->percent;
            $pb = $summaries[$b]->percent;
            if ($pa === null || $pb === null) {
                $bynull = ($pa === null) <=> ($pb === null);
                if ($bynull !== 0) {
                    return $bynull;
                }
            } else if ($pa != $pb) {
                return $dir === 'desc' ? $pb <=> $pa : $pa <=> $pb;
            }
            return $position[$a] <=> $position[$b];
        });
        return $students;
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
            roll_page::FILTER_ALL => true,
            roll_page::FILTER_REGULAR => $categoryid === 0,
            default => $categoryid === (int)$filter,
        };
    }
}
