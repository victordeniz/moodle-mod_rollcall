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
 * Creates or edits a student category.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use mod_rollcall\local\category_manager;
use mod_rollcall\persistent\category;

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('mod_rollcall_categories');
$returnurl = new moodle_url('/mod/rollcall/categories.php');
$PAGE->set_url(new moodle_url('/mod/rollcall/editcategory.php', ['id' => $id]));

$title = get_string($id ? 'editcategory' : 'addcategory', 'mod_rollcall');
$PAGE->set_title($title);
$PAGE->navbar->add($title);

$form = new \mod_rollcall\form\category_form($PAGE->url);
if ($id) {
    $category = new category($id);
    $form->set_data(['id' => $id, 'name' => $category->get('name'), 'color' => $category->get('color')]);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    if ($data->id) {
        category_manager::update((int)$data->id, trim($data->name), $data->color);
    } else {
        category_manager::create(trim($data->name), $data->color);
    }
    redirect($returnurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
