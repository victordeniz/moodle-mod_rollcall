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
 * Attendance statuses of a roll: list, sort, show/hide, delete and restore the defaults.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core\output\notification;
use mod_rollcall\local\status_manager;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$statusid = optional_param('statusid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'rollcall');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/rollcall:managestatuses', $context);
$rollcallid = (int)$cm->instance;

$url = new moodle_url('/mod/rollcall/statuses.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('statuses', 'mod_rollcall'));
$PAGE->set_heading(format_string($course->fullname));

if ($action !== '') {
    require_sesskey();
    $options = ['context' => $context];
    switch ($action) {
        case 'up':
        case 'down':
            $status = status_manager::get_for_rollcall($statusid, $rollcallid);
            status_manager::move((int)$status->get('id'), $action === 'up' ? -1 : 1, $context);
            redirect($url);
        case 'show':
        case 'hide':
            $status = status_manager::get_for_rollcall($statusid, $rollcallid);
            status_manager::set_visible((int)$status->get('id'), $action === 'show', $context);
            redirect($url);
        case 'delete':
            $status = status_manager::get_for_rollcall($statusid, $rollcallid);
            if (!$confirm) {
                echo $OUTPUT->header();
                echo $OUTPUT->confirm(
                    get_string('confirmdeletestatus', 'mod_rollcall', format_string($status->get('name'), true, $options)),
                    new moodle_url($url, ['action' => 'delete', 'statusid' => $statusid, 'confirm' => 1, 'sesskey' => sesskey()]),
                    $url
                );
                echo $OUTPUT->footer();
                die();
            }
            try {
                status_manager::delete((int)$status->get('id'), $context);
            } catch (moodle_exception $e) {
                if ($e->errorcode !== 'statusinuse') {
                    throw $e;
                }
                redirect($url, get_string('statusinuse', 'mod_rollcall'), null, notification::NOTIFY_ERROR);
            }
            redirect($url, get_string('statusdeleted', 'mod_rollcall'), null, notification::NOTIFY_SUCCESS);
        case 'restoredefaults':
            if (!$confirm) {
                echo $OUTPUT->header();
                echo $OUTPUT->confirm(
                    get_string('confirmrestorestatuses', 'mod_rollcall'),
                    new moodle_url($url, ['action' => 'restoredefaults', 'confirm' => 1, 'sesskey' => sesskey()]),
                    $url
                );
                echo $OUTPUT->footer();
                die();
            }
            $created = status_manager::add_missing_defaults(
                $rollcallid,
                status_manager::lang_for_course((int)$course->id),
                $context
            );
            redirect($url, get_string('defaultsrestored', 'mod_rollcall', $created), null, notification::NOTIFY_SUCCESS);
        default:
            throw new moodle_exception('invalidaction', 'error');
    }
}

$statuses = array_values(status_manager::get_all($rollcallid));
$rows = [];
foreach ($statuses as $index => $status) {
    $rowid = (int)$status->get('id');
    $actionurl = fn(string $action) => (new moodle_url($url, ['action' => $action, 'statusid' => $rowid,
        'sesskey' => sesskey()]))->out(false);
    $visible = (bool)$status->get('visible');
    $rows[] = [
        'name' => format_string($status->get('name'), true, ['context' => $context]),
        'visible' => $visible,
        'weight' => (int)$status->get('weight'),
        'upurl' => $index > 0 ? $actionurl('up') : null,
        'downurl' => $index < count($statuses) - 1 ? $actionurl('down') : null,
        'editurl' => (new moodle_url('/mod/rollcall/editstatus.php', ['id' => $cm->id, 'statusid' => $rowid]))->out(false),
        'toggleurl' => $actionurl($visible ? 'hide' : 'show'),
        'deleteurl' => status_manager::is_in_use($rowid) ? null : $actionurl('delete'),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('statuses', 'mod_rollcall'));
echo $OUTPUT->render_from_template('mod_rollcall/statuses', [
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'addurl' => (new moodle_url('/mod/rollcall/editstatus.php', ['id' => $cm->id]))->out(false),
    'restoreurl' => (new moodle_url($url, ['action' => 'restoredefaults', 'sesskey' => sesskey()]))->out(false),
]);
echo $OUTPUT->footer();
