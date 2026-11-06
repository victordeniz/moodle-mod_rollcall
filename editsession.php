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
 * Creates or edits a session of a roll.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core\output\notification;
use mod_rollcall\local\session_manager;

$id = required_param('id', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$group = optional_param('group', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'rollcall');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/rollcall:managesessions', $context);

$returnurl = new moodle_url('/mod/rollcall/view.php', ['id' => $cm->id, 'group' => $group]);
$PAGE->set_url(new moodle_url(
    '/mod/rollcall/editsession.php',
    ['id' => $cm->id, 'sessionid' => $sessionid, 'group' => $group]
));
$title = get_string($sessionid ? 'editsession' : 'addsession', 'mod_rollcall');
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('sessions', 'mod_rollcall'), $returnurl);
$PAGE->navbar->add($title);

$groupmode = groups_get_activity_groupmode($cm);
$groupoptions = session_manager::group_options($cm);
$requiregroupchoice = false;
$original = null;

if (!$sessionid && !session_manager::can_create($cm)) {
    throw new required_capability_exception($context, 'mod/rollcall:managesessions', 'nopermissions', '');
}
if ($sessionid) {
    $original = session_manager::get_for_rollcall($sessionid, (int)$cm->instance);
    if (!session_manager::can_manage($cm, (int)$original->get('groupid'))) {
        throw new required_capability_exception($context, 'mod/rollcall:managesessions', 'nopermissions', '');
    }
    $savedgroup = (int)$original->get('groupid');
    if ($groupmode != NOGROUPS && !isset($groupoptions[$savedgroup])) {
        // Deleted group: keep it selectable so that saving does not move the session by accident.
        $savedname = $savedgroup ? $DB->get_field('groups', 'name', ['id' => $savedgroup]) : false;
        $groupoptions[$savedgroup] = $savedname !== false
            ? format_string($savedname, true, ['context' => $context])
            : get_string('deletedgroup', 'mod_rollcall');
    }
    $defaults = [
        'id' => $cm->id,
        'sessionid' => $sessionid,
        'name' => $original->get('name'),
        'sessdate' => (int)$original->get('sessdate'),
        'durationminutes' => intdiv((int)$original->get('duration'), 60),
        'groupid' => $savedgroup,
    ];
} else {
    $now = time();
    $defaults = [
        'id' => $cm->id,
        'sessionid' => 0,
        'name' => session_manager::default_name($now),
        'sessdate' => $now,
        'durationminutes' => 60,
    ];
    if ($groupmode != NOGROUPS) {
        if (isset($groupoptions[$group])) {
            $defaults['groupid'] = $group;
        } else if (count($groupoptions) === 1) {
            $defaults['groupid'] = array_key_first($groupoptions);
        } else {
            $requiregroupchoice = true;
        }
    }
}

$form = new \mod_rollcall\form\session_form($PAGE->url, [
    'groupoptions' => $groupmode == NOGROUPS ? [] : $groupoptions,
    'requiregroupchoice' => $requiregroupchoice,
]);
$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    // Without a group field (no groups), a new session goes to the whole activity and an edited one keeps its group.
    if (isset($data->groupid)) {
        $newgroup = (int)$data->groupid;
    } else {
        $newgroup = $original ? (int)$original->get('groupid') : 0;
    }
    // The posted group and session are untrusted: check them on the server.
    if (
        !session_manager::can_manage($cm, $newgroup)
        || ($original && !session_manager::can_manage($cm, (int)$original->get('groupid')))
    ) {
        throw new required_capability_exception($context, 'mod/rollcall:managesessions', 'nopermissions', '');
    }
    $duration = (int)$data->durationminutes * 60;
    if ($original) {
        $session = session_manager::update(
            (int)$original->get('id'),
            $newgroup,
            $data->name,
            (int)$data->sessdate,
            $duration,
            $context
        );
        $message = get_string('sessionupdated', 'mod_rollcall');
    } else {
        $session = session_manager::create(
            (int)$cm->instance,
            $newgroup,
            $data->name,
            (int)$data->sessdate,
            $duration,
            $context
        );
        $message = get_string('sessioncreated', 'mod_rollcall');
    }
    redirect(
        new moodle_url('/mod/rollcall/view.php', [
            'id' => $cm->id,
            'group' => $newgroup,
            'highlight' => $session->get('id'),
        ]),
        $message,
        null,
        notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
