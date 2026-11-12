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
 * Take attendance of a session.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_rollcall\local\session_manager;
use mod_rollcall\output\roll_page;
use mod_rollcall\output\take_page;

$sessionid = required_param('sessionid', PARAM_INT);
$filter = optional_param('filter', roll_page::FILTER_ALL, PARAM_ALPHANUM);
$sort = optional_param('sort', take_page::SORT_LASTNAME, PARAM_ALPHA);
$sort = $sort === take_page::SORT_FIRSTNAME ? take_page::SORT_FIRSTNAME : take_page::SORT_LASTNAME;
$dir = optional_param('dir', 'asc', PARAM_ALPHA) === 'desc' ? 'desc' : 'asc';
// Every student is shown unless paging is requested; the page only matters when paged.
$paged = (bool)optional_param('paged', 0, PARAM_BOOL);
$page = $paged ? max(0, optional_param('page', 0, PARAM_INT)) : 0;

// The roll is derived from the session, so a session id cannot be used against another activity.
$rollcallid = (int)$DB->get_field('rollcall_session', 'rollcallid', ['id' => $sessionid], MUST_EXIST);
[$course, $cm] = get_course_and_cm_from_instance($rollcallid, 'rollcall');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/rollcall:takeattendance', $context);
$session = session_manager::get_for_rollcall($sessionid, $rollcallid);
if (!session_manager::can_take($cm, $session)) {
    throw new required_capability_exception($context, 'mod/rollcall:takeattendance', 'nopermissions', '');
}

$url = new moodle_url('/mod/rollcall/take.php', ['sessionid' => $sessionid]);
foreach (['filter' => $filter, 'sort' => $sort, 'dir' => $dir, 'paged' => (int)$paged, 'page' => $page] as $name => $value) {
    if ($value !== roll_page::FILTER_ALL && $value !== 0 && !($name === 'sort' && $value === take_page::SORT_LASTNAME)
            && !($name === 'dir' && $value === 'asc')) {
        $url->param($name, $value);
    }
}
$PAGE->set_url($url);
$PAGE->set_title(get_string('takeattendance', 'mod_rollcall'));
$PAGE->set_heading(format_string($course->fullname));
$sessionname = format_string($session->get('name'), true, ['context' => $context]);
$PAGE->navbar->add(get_string('sessions', 'mod_rollcall'), new moodle_url('/mod/rollcall/view.php', ['id' => $cm->id]));
$PAGE->navbar->add($sessionname);

$perpage = (int)get_config('mod_rollcall', 'perpage') ?: 25;
$renderable = new take_page($cm, $session, $filter, $sort, $dir, $page, $perpage, $paged);

// Always started: it does nothing without a table, and with students the per-row control can still clear
// a status that is now hidden even when there are no visible statuses left.
$PAGE->requires->js_call_amd('mod_rollcall/take', 'init', [$cm->id, $sessionid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('takeattendance', 'mod_rollcall'));
echo $OUTPUT->render($renderable);
echo $OUTPUT->footer();
