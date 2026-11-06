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

namespace mod_rollcall\output;

use mod_rollcall\local\session_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/group/lib.php');

/**
 * Tests for the sessions front page.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sessions_page::class)]
final class sessions_page_test extends \advanced_testcase {
    /**
     * Tests the rows, links and escaping of the page.
     */
    public function test_sessions_page_rows(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $g1 = $gen->create_group(['courseid' => $course->id, 'name' => 'G1']);
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $student = $gen->create_and_enrol($course, 'student');
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $teacher->id]);
        $managerid = $gen->create_user()->id;
        $gen->role_assign($DB->get_field('role', 'id', ['shortname' => 'manager']), $managerid, \context_system::instance()->id);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $roll->cmid]);
        rebuild_course_cache($course->id, true);
        $cm = get_fast_modinfo($course->id)->get_cm($roll->cmid);

        session_manager::create((int)$roll->id, 0, 'Whole', 1000, 3600);
        $hostile = session_manager::create((int)$roll->id, (int)$g1->id, 'placeholder', 2000, 5400);
        // The persistent rejects markup, so store it directly to prove the page escapes whatever the table holds.
        $DB->set_field('rollcall_session', 'name', 'A & <b>B</b>', ['id' => $hostile->get('id')]);

        $render = function (\cm_info $cm, int $groupid) use ($PAGE) {
            return (new sessions_page($cm, $groupid, 0))->export_for_template($PAGE->get_renderer('core'));
        };

        $this->setUser($teacher);
        $data = $render($cm, (int)$g1->id);
        $this->assertTrue($data['hasrows']);
        $this->assertCount(2, $data['rows']);
        // format_string() strips the markup and escapes the ampersand once, so the triple-brace output is safe.
        $this->assertSame('A &amp; B', $data['rows'][0]['name']);
        $this->assertSame('G1', $data['rows'][0]['group']);
        $this->assertNotNull($data['rows'][0]['editurl']);
        $this->assertNotNull($data['rows'][0]['deleteurl']);
        $this->assertSame('Whole', $data['rows'][1]['name']);
        $this->assertSame(get_string('wholeactivity', 'mod_rollcall'), $data['rows'][1]['group']);
        $this->assertNull($data['rows'][1]['editurl']);
        $this->assertNull($data['rows'][1]['deleteurl']);
        $this->assertNotNull($data['addurl']);

        // Rendering through the template must not trigger debugging (e.g. missing strings).
        $html = $PAGE->get_renderer('core')->render(new sessions_page($cm, (int)$g1->id, 0));
        $this->assertStringContainsString('A &amp; B', $html);
        $this->assertDebuggingNotCalled();

        $this->setUser($student);
        $this->assertFalse(session_manager::can_create($cm));

        groups_delete_group($g1);
        rebuild_course_cache($course->id, true);
        $cm = get_fast_modinfo($course->id)->get_cm($roll->cmid);
        $this->setUser($managerid);
        $data = $render($cm, 0);
        $this->assertSame(get_string('deletedgroup', 'mod_rollcall'), $data['rows'][0]['group']);
        $this->assertNotNull($data['rows'][0]['editurl']);
    }
}
