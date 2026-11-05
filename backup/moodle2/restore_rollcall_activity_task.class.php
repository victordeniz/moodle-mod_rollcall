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
 * Restore task for mod_rollcall.
 *
 * @package    mod_rollcall
 * @category   backup
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/rollcall/backup/moodle2/restore_rollcall_stepslib.php');

/**
 * Restores a roll.
 */
class restore_rollcall_activity_task extends restore_activity_task {
    /**
     * No specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the restore steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_rollcall_activity_structure_step('rollcall_structure', 'rollcall.xml'));
    }

    /**
     * Contents to decode links in.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('rollcall', ['intro'], 'rollcall')];
    }

    /**
     * Rules to decode links.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('ROLLCALLVIEWBYID', '/mod/rollcall/view.php?id=$1', 'course_module'),
            new restore_decode_rule('ROLLCALLINDEX', '/mod/rollcall/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Legacy log rules (none).
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Legacy course log rules (none).
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
