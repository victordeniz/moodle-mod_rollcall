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
 * Site-wide catalogue of student categories: list, sort, show/hide and delete.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use mod_rollcall\local\category_manager;
use mod_rollcall\local\palette;
use mod_rollcall\persistent\category;

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

admin_externalpage_setup('mod_rollcall_categories');
$url = new moodle_url('/mod/rollcall/categories.php');
$systemcontext = context_system::instance();

if ($action !== '' && $id) {
    require_sesskey();
    switch ($action) {
        case 'up':
        case 'down':
            category_manager::move($id, $action === 'up' ? -1 : 1);
            redirect($url);
        case 'show':
        case 'hide':
            category_manager::set_visible($id, $action === 'show');
            redirect($url);
        case 'delete':
            $category = new category($id);
            if (!$confirm) {
                echo $OUTPUT->header();
                echo $OUTPUT->confirm(
                    get_string('confirmdeletecategory', 'mod_rollcall',
                        format_string($category->get('name'), true, ['context' => $systemcontext])),
                    new moodle_url($url, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
                    $url
                );
                echo $OUTPUT->footer();
                die();
            }
            try {
                category_manager::delete($id);
            } catch (moodle_exception $e) {
                if ($e->errorcode !== 'categoryinuse') {
                    throw $e;
                }
                redirect($url, get_string('categoryinuse', 'mod_rollcall'), null, \core\output\notification::NOTIFY_ERROR);
            }
            redirect($url, get_string('categorydeleted', 'mod_rollcall'), null, \core\output\notification::NOTIFY_SUCCESS);
        default:
            throw new moodle_exception('invalidaction', 'error');
    }
}

$counts = $DB->get_records_sql_menu('SELECT categoryid, COUNT(1) FROM {rollcall_usercat} GROUP BY categoryid');
$colors = palette::options();
$categories = array_values(category_manager::get_all());
$rows = [];
foreach ($categories as $index => $category) {
    $categoryid = (int)$category->get('id');
    $actionurl = fn(string $action) => (new moodle_url($url, ['action' => $action, 'id' => $categoryid,
        'sesskey' => sesskey()]))->out(false);
    $count = (int)($counts[$categoryid] ?? 0);
    $visible = (bool)$category->get('visible');
    $rows[] = [
        'name' => format_string($category->get('name'), true, ['context' => $systemcontext]),
        'color' => $category->get('color'),
        'colorname' => $colors[$category->get('color')] ?? $category->get('color'),
        'count' => $count,
        'visible' => $visible,
        'upurl' => $index > 0 ? $actionurl('up') : null,
        'downurl' => $index < count($categories) - 1 ? $actionurl('down') : null,
        'editurl' => (new moodle_url('/mod/rollcall/editcategory.php', ['id' => $categoryid]))->out(false),
        'toggleurl' => $actionurl($visible ? 'hide' : 'show'),
        'deleteurl' => $count === 0 ? $actionurl('delete') : null,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecategories', 'mod_rollcall'));
echo $OUTPUT->render_from_template('mod_rollcall/categories', [
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'addurl' => (new moodle_url('/mod/rollcall/editcategory.php'))->out(false),
]);
echo $OUTPUT->footer();
