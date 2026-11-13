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
namespace mod_rollcall;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/mod/rollcall/db/upgrade.php');

/**
 * Tests for the upgrade steps of the plugin.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_rollcall_upgrade')]
final class upgrade_test extends \advanced_testcase {
    /**
     * Tests the upgrade adds the grade columns, leaving the existing rolls without a grade.
     */
    public function test_upgrade_adds_the_grade_columns(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        $course = $this->getDataGenerator()->create_course();
        $roll = $this->getDataGenerator()->create_module('rollcall', ['course' => $course->id, 'grade' => 10]);

        // Back to the table of the version before the grades.
        $table = new \xmldb_table('rollcall');
        foreach (['gradessynced', 'grade'] as $name) {
            $field = new \xmldb_field($name);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        set_config('version', 2026110901, 'mod_rollcall');
        $this->assertTrue(xmldb_rollcall_upgrade(2026110901));
        $this->assertGreaterThanOrEqual(2026111200, (int)get_config('mod_rollcall', 'version'));

        $columns = $DB->get_columns('rollcall', false);
        $this->assertArrayHasKey('grade', $columns);
        $this->assertArrayHasKey('gradessynced', $columns);
        $record = $DB->get_record('rollcall', ['id' => $roll->id], '*', MUST_EXIST);
        $this->assertSame(0, (int)$record->grade);
        $this->assertSame(0, (int)$record->gradessynced);
    }
}
