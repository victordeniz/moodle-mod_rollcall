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

    /**
     * Tests the take attendance link follows the group rule.
     */
    public function test_sessions_page_take_link(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $g1 = $gen->create_group(['courseid' => $course->id, 'name' => 'G1']);
        $g2 = $gen->create_group(['courseid' => $course->id, 'name' => 'G2']);
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $teacher->id]);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $roll->cmid]);
        rebuild_course_cache($course->id, true);
        $cm = get_fast_modinfo($course->id)->get_cm($roll->cmid);

        $mine = session_manager::create((int)$roll->id, (int)$g1->id, 'Mine', 1000, 3600);
        session_manager::create((int)$roll->id, (int)$g2->id, 'Other', 2000, 3600);

        $this->setUser($teacher);
        $renderer = $PAGE->get_renderer('core');
        $data = (new sessions_page($cm, (int)$g1->id, 0))->export_for_template($renderer);
        $this->assertCount(1, $data['rows']);
        $this->assertStringContainsString('take.php', $data['rows'][0]['takeurl']);
        $this->assertStringContainsString('sessionid=' . $mine->get('id'), $data['rows'][0]['takeurl']);

        // As a manager in the other group's view the link exists; the teacher cannot take the other group.
        $this->setAdminUser();
        $data = (new sessions_page($cm, 0, 0))->export_for_template($renderer);
        $this->assertNotNull($data['rows'][0]['takeurl']);
        $this->assertNotNull($data['rows'][1]['takeurl']);
        $this->setUser($teacher);
        $other = session_manager::get_for_rollcall(
            (int)$DB->get_field('rollcall_session', 'id', ['name' => 'Other']),
            (int)$roll->id
        );
        $this->assertFalse(session_manager::can_take($cm, $other));
    }

    /**
     * Tests the block to update the grades depends on the grade of the roll and on the capability.
     */
    public function test_sessions_page_grades_block(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $plain = $gen->create_module('rollcall', ['course' => $course->id]);
        $graded = $gen->create_module('rollcall', ['course' => $course->id, 'grade' => 10]);
        $editing = $gen->create_and_enrol($course, 'editingteacher');
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $student = $gen->create_and_enrol($course, 'student');
        $renderer = $PAGE->get_renderer('core');
        $page = fn(int $cmid) => new sessions_page(get_fast_modinfo($course->id)->get_cm($cmid), 0, 0);

        // A roll without a grade has no block.
        $this->setUser($editing);
        $this->assertNull($page($plain->cmid)->export_for_template($renderer)['grades']);
        $this->assertStringNotContainsString('updategrades', $renderer->render($page($plain->cmid)));

        // Never sent.
        $data = $page($graded->cmid)->export_for_template($renderer);
        $this->assertNull($data['grades']['synced']);
        $this->assertSame((int)$graded->cmid, $data['grades']['id']);
        $this->assertSame(0, $data['grades']['group']);
        $this->assertSame(sesskey(), $data['grades']['sesskey']);
        $html = $renderer->render($page($graded->cmid));
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('name="action" value="updategrades"', $html);
        $this->assertStringContainsString(get_string('gradesneversynced', 'mod_rollcall'), $html);

        // Sent before: the time is shown.
        $DB->set_field('rollcall', 'gradessynced', 1000, ['id' => $graded->id]);
        $when = userdate(1000, get_string('strftimedatetimeshort', 'langconfig'));
        $this->assertSame($when, $page($graded->cmid)->export_for_template($renderer)['grades']['synced']);
        $this->assertStringContainsString(
            get_string('gradeslastsynced', 'mod_rollcall', $when),
            $renderer->render($page($graded->cmid))
        );

        // The non-editing teacher can update the grades too; the student cannot.
        $this->setUser($teacher);
        $this->assertNotNull($page($graded->cmid)->export_for_template($renderer)['grades']);
        $this->setUser($student);
        $this->assertNull($page($graded->cmid)->export_for_template($renderer)['grades']);

        // Nor a teacher whose capability is prohibited in the activity.
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('mod/rollcall:updategrades', CAP_PROHIBIT, $roleid, \context_module::instance($graded->cmid));
        $this->setUser($teacher);
        $this->assertNull($page($graded->cmid)->export_for_template($renderer)['grades']);
        $this->assertStringNotContainsString('updategrades', $renderer->render($page($graded->cmid)));
    }
}
