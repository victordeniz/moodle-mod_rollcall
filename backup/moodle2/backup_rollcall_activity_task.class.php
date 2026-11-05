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
 * Backup task for mod_rollcall.
 *
 * @package    mod_rollcall
 * @category   backup
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/rollcall/backup/moodle2/backup_rollcall_stepslib.php');

/**
 * Backs up a roll. Student classifications are never included: they belong to the course and to its academic year.
 */
class backup_rollcall_activity_task extends backup_activity_task {
    /**
     * No specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the backup steps.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_rollcall_activity_structure_step('rollcall_structure', 'rollcall.xml'));
    }

    /**
     * Encodes links to the module pages.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace("/({$base}\/mod\/rollcall\/index.php\?id\=)([0-9]+)/", '$@ROLLCALLINDEX*$2@$', $content);
        return preg_replace("/({$base}\/mod\/rollcall\/view.php\?id\=)([0-9]+)/", '$@ROLLCALLVIEWBYID*$2@$', $content);
    }
}
