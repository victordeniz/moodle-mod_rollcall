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

/**
 * Attendance report of a roll: per student, the count of each status and the attendance percentage.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_rollcall\output\report_page;
use mod_rollcall\output\roll_page;

$id = required_param('id', PARAM_INT);
$filter = optional_param('filter', roll_page::FILTER_ALL, PARAM_ALPHANUM);
$sort = optional_param('sort', report_page::SORT_LASTNAME, PARAM_ALPHA);
$sort = in_array($sort, [report_page::SORT_FIRSTNAME, report_page::SORT_PERCENT], true) ? $sort : report_page::SORT_LASTNAME;
$dir = optional_param('dir', 'asc', PARAM_ALPHA) === 'desc' ? 'desc' : 'asc';
$page = max(0, optional_param('page', 0, PARAM_INT));

[$course, $cm] = get_course_and_cm_from_cmid($id, 'rollcall');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/rollcall:viewreport', $context);

$url = new moodle_url('/mod/rollcall/report.php', ['id' => $cm->id]);
foreach (['filter' => $filter, 'sort' => $sort, 'dir' => $dir, 'page' => $page] as $name => $value) {
    if ($value !== roll_page::FILTER_ALL && $value !== 0 && !($name === 'sort' && $value === report_page::SORT_LASTNAME)
            && !($name === 'dir' && $value === 'asc')) {
        $url->param($name, $value);
    }
}
$PAGE->set_url($url);
$PAGE->set_title(get_string('report', 'mod_rollcall'));
$PAGE->set_heading(format_string($course->fullname));

\mod_rollcall\event\report_viewed::create(['context' => $context])->trigger();

$groupmode = groups_get_activity_groupmode($cm);
$groupid = (int)groups_get_activity_group($cm, true);
$nogroup = $groupmode == SEPARATEGROUPS && $groupid === 0 && !has_capability('moodle/site:accessallgroups', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'mod_rollcall'));
groups_print_activity_menu($cm, $url);
if ($nogroup) {
    echo $OUTPUT->notification(get_string('notingroup', 'mod_rollcall'), \core\output\notification::NOTIFY_INFO);
} else {
    $perpage = (int)get_config('mod_rollcall', 'perpage') ?: 25;
    echo $OUTPUT->render(new report_page($cm, $groupid, $filter, $sort, $dir, $page, $perpage, time()));
}
echo $OUTPUT->footer();
