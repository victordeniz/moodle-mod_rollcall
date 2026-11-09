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
 * Creates or edits an attendance status of a roll.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_rollcall\local\status_manager;

$id = required_param('id', PARAM_INT);
$statusid = optional_param('statusid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'rollcall');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/rollcall:managestatuses', $context);

$returnurl = new moodle_url('/mod/rollcall/statuses.php', ['id' => $cm->id]);
$PAGE->set_url(new moodle_url('/mod/rollcall/editstatus.php', ['id' => $cm->id, 'statusid' => $statusid]));
$title = get_string($statusid ? 'editstatus' : 'addstatus', 'mod_rollcall');
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('statuses', 'mod_rollcall'), $returnurl);
$PAGE->navbar->add($title);

$form = new \mod_rollcall\form\status_form($PAGE->url, ['rollcallid' => (int)$cm->instance]);
if ($statusid) {
    $status = status_manager::get_for_rollcall($statusid, (int)$cm->instance);
    $form->set_data(['id' => $cm->id, 'statusid' => $statusid, 'name' => $status->get('name')]);
} else {
    $form->set_data(['id' => $cm->id]);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    if ($data->statusid) {
        status_manager::get_for_rollcall((int)$data->statusid, (int)$cm->instance);
        status_manager::update((int)$data->statusid, $data->name, 0, $context);
    } else {
        status_manager::create((int)$cm->instance, $data->name, 0, $context);
    }
    redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
