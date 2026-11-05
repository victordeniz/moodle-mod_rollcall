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
 * Site administration entries for mod_rollcall.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var admin_root $ADMIN */
/** @var \core\plugininfo\mod $module */

$folder = new admin_category('modrollcallfolder', new lang_string('pluginname', 'mod_rollcall'),
    $module->is_enabled() === false);
$ADMIN->add('modsettings', $folder);
$ADMIN->add('modrollcallfolder', new admin_externalpage(
    'mod_rollcall_categories',
    new lang_string('managecategories', 'mod_rollcall'),
    new moodle_url('/mod/rollcall/categories.php'),
    'moodle/site:config',
    $module->is_enabled() === false
));

// The module has no settings of its own, only the catalogue page.
$settings = null;
