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

namespace mod_rollcall\output;

use mod_rollcall\local\attendance_manager;
use mod_rollcall\local\category_manager;
use mod_rollcall\local\classification;
use mod_rollcall\local\roster;
use mod_rollcall\local\session_manager;
use mod_rollcall\local\status_manager;
use mod_rollcall\persistent\session;

/**
 * Take attendance: the students of a session with their status, filterable, sortable and paged.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class take_page implements \renderable, \templatable {
    /** Sort by last name. */
    public const SORT_LASTNAME = 'lastname';
    /** Sort by first name. */
    public const SORT_FIRSTNAME = 'firstname';

    /**
     * Constructor.
     *
     * @param \cm_info $cm The roll
     * @param session $session The session; the caller has checked that the user can take it
     * @param string $filter 'all', 'regular' or a category id
     * @param string $sort 'lastname' or 'firstname'
     * @param string $dir 'asc' or 'desc'
     * @param int $page Zero-based page, clamped to the existing ones
     * @param int $perpage Students per page
     */
    public function __construct(
        /** @var \cm_info The roll */
        protected \cm_info $cm,
        /** @var session The session */
        protected session $session,
        /** @var string 'all', 'regular' or a category id */
        protected string $filter,
        /** @var string 'lastname' or 'firstname' */
        protected string $sort,
        /** @var string 'asc' or 'desc' */
        protected string $dir,
        /** @var int Zero-based page */
        protected int $page,
        /** @var int Students per page */
        protected int $perpage,
    ) {
    }

    /**
     * Exports the data for the mod_rollcall/take_page template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $cm = $this->cm;
        $context = $cm->context;
        $systemcontext = \context_system::instance();
        $sessionid = (int)$this->session->get('id');
        $rollcallid = (int)$cm->instance;
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
        $sort = $this->sort === self::SORT_FIRSTNAME ? self::SORT_FIRSTNAME : self::SORT_LASTNAME;
        $dir = $this->dir === 'desc' ? 'desc' : 'asc';

        $students = roster::get_students($cm, session_manager::roster_groupid($cm, $this->session));
        $effective = classification::get_effective((int)$cm->course, array_keys($students));
        $matching = [];
        foreach ($students as $userid => $user) {
            $categoryid = isset($effective[$userid]) ? (int)$effective[$userid]->get('id') : 0;
            if ($this->matches($filter, $categoryid)) {
                $user->categoryid = $categoryid;
                $matching[$userid] = $user;
            }
        }
        // Locale-aware sort on the chosen name.
        \core_collator::asort_objects_by_property($matching, $sort);
        $matching = $dir === 'desc' ? array_reverse($matching, true) : $matching;

        $total = count($matching);
        $lastpage = $total > 0 ? (int)ceil($total / $this->perpage) - 1 : 0;
        $page = max(0, min($this->page, $lastpage));
        $pagestudents = array_slice($matching, $page * $this->perpage, $this->perpage, true);

        $current = attendance_manager::get_for_users($sessionid, array_keys($pagestudents));
        $statuses = status_manager::get_all($rollcallid);
        $visible = [];
        $hiddennames = [];
        foreach ($statuses as $status) {
            $name = format_string($status->get('name'), true, ['context' => $context]);
            if ($status->get('visible')) {
                $visible[] = ['id' => (int)$status->get('id'), 'name' => $name];
            } else {
                $hiddennames[(int)$status->get('id')] = get_string('statushidden', 'mod_rollcall', $name);
            }
        }
        $nostatus = ['id' => 0, 'name' => get_string('nostatus', 'mod_rollcall')];

        $rows = [];
        foreach ($pagestudents as $userid => $user) {
            $statusid = $current[$userid] ?? 0;
            $options = [];
            foreach (array_merge([$nostatus], $visible) as $option) {
                $options[] = $option + ['selected' => $option['id'] === $statusid];
            }
            // A hidden status can no longer be chosen, but it is kept and shown where it is already assigned.
            if (isset($hiddennames[$statusid])) {
                $options[] = ['id' => $statusid, 'name' => $hiddennames[$statusid], 'selected' => true];
            }
            $display = $categories[$user->categoryid] ?? $regular;
            $rows[] = [
                'userid' => (int)$userid,
                'fullname' => fullname($user),
                'picture' => $output->user_picture($user, ['courseid' => $cm->course, 'size' => 35]),
                'categoryname' => $display['name'],
                'color' => $display['color'],
                'hascategory' => $user->categoryid !== 0,
                'statusid' => $statusid,
                'options' => $options,
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

        $params = ['sessionid' => $sessionid, 'filter' => $filter, 'sort' => $sort, 'dir' => $dir];
        $baseurl = new \moodle_url('/mod/rollcall/take.php', $params);
        $sortlinks = [];
        foreach ([self::SORT_FIRSTNAME, self::SORT_LASTNAME] as $key) {
            $active = $key === $sort;
            $sortlinks[] = [
                'name' => get_string($key),
                'url' => (new \moodle_url($baseurl, ['sort' => $key, 'dir' => $active && $dir === 'asc' ? 'desc' : 'asc']))->out(false),
                'active' => $active,
                'dir' => $active ? $dir : '',
                'arrow' => $active ? ($dir === 'asc' ? '▲' : '▼') : '',
            ];
        }

        $groups = groups_get_all_groups($cm->course);
        $groupid = (int)$this->session->get('groupid');
        if ($groupid === 0) {
            $groupname = get_string('wholeactivity', 'mod_rollcall');
        } else if (isset($groups[$groupid])) {
            $groupname = format_string($groups[$groupid]->name, true, ['context' => $context]);
        } else {
            $groupname = get_string('deletedgroup', 'mod_rollcall');
        }

        return [
            'cmid' => (int)$cm->id,
            'sessionid' => $sessionid,
            'sessionname' => format_string($this->session->get('name'), true, ['context' => $context]),
            'sessiondate' => userdate((int)$this->session->get('sessdate'), get_string('strftimedatetimeshort', 'langconfig')),
            'duration' => format_time((int)$this->session->get('duration')),
            'groupname' => $groupname,
            'backurl' => (new \moodle_url('/mod/rollcall/view.php', ['id' => $cm->id, 'group' => $groupid]))->out(false),
            'takeurl' => (new \moodle_url('/mod/rollcall/take.php'))->out(false),
            'sort' => $sort,
            'dir' => $dir,
            'filters' => $filters,
            'sortlinks' => $sortlinks,
            'rows' => $rows,
            'bulkstatuses' => array_merge([$nostatus], $visible),
            'pagingbar' => $output->paging_bar($total, $page, $this->perpage, $baseurl),
            'hasstatuses' => !empty($visible),
            'managestatusesurl' => has_capability('mod/rollcall:managestatuses', $context)
                ? (new \moodle_url('/mod/rollcall/statuses.php', ['id' => $cm->id]))->out(false) : null,
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
            roll_page::FILTER_ALL => true,
            roll_page::FILTER_REGULAR => $categoryid === 0,
            default => $categoryid === (int)$filter,
        };
    }
}
