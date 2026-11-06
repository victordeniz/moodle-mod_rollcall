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
 * Shows the roll: the students of the course with their category.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
// Old links to the roll with a filter go to the Students tab, which now holds the roll.
$filter = optional_param('filter', '', PARAM_ALPHANUM);
if ($filter !== '') {
    redirect(new moodle_url('/mod/rollcall/students.php', ['id' => $id, 'filter' => $filter]));
}
$filter = \mod_rollcall\output\roll_page::FILTER_ALL;

[$course, $cm] = get_course_and_cm_from_cmid($id, 'rollcall');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/rollcall:view', $context);
$roll = $DB->get_record('rollcall', ['id' => $cm->instance], '*', MUST_EXIST);

$url = new moodle_url('/mod/rollcall/view.php', ['id' => $cm->id]);
if ($filter !== \mod_rollcall\output\roll_page::FILTER_ALL) {
    $url->param('filter', $filter);
}
$PAGE->set_url($url);
$PAGE->set_title(format_string($roll->name));
$PAGE->set_heading(format_string($course->fullname));

$event = \mod_rollcall\event\course_module_viewed::create(['objectid' => $roll->id, 'context' => $context]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('rollcall', $roll);
$event->trigger();

$canclassify = has_capability('mod/rollcall:classify', $context);
$groupmode = groups_get_activity_groupmode($cm);
$groupid = (int)groups_get_activity_group($cm, true);
// In separate groups, a user without access to all groups and without groups of their own sees nobody.
$nogroup = $groupmode == SEPARATEGROUPS && $groupid === 0 && !has_capability('moodle/site:accessallgroups', $context);

if ($canclassify && !$nogroup) {
    $PAGE->requires->js_call_amd('mod_rollcall/classify', 'init', [$cm->id]);
}

echo $OUTPUT->header();
groups_print_activity_menu($cm, $url);
if ($nogroup) {
    echo $OUTPUT->notification(get_string('notingroup', 'mod_rollcall'), \core\output\notification::NOTIFY_INFO);
} else {
    echo $OUTPUT->render(new \mod_rollcall\output\roll_page($cm, $groupid, $filter, $canclassify));
}
echo $OUTPUT->footer();
