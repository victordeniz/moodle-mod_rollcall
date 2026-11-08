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

use mod_rollcall\local\session_manager;

/**
 * Tests for the take attendance capability.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attendance_permissions_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private $course;
    /** @var \stdClass Roll instance. */
    private $roll;
    /** @var \stdClass Group G1. */
    private $g1;
    /** @var \stdClass Group G2. */
    private $g2;
    /** @var \stdClass Teacher (non-editing) in G1. */
    private $teacher;
    /** @var \stdClass Editing teacher without group. */
    private $editing;
    /** @var \stdClass Manager. */
    private $manager;
    /** @var \stdClass Student. */
    private $student;

    /**
     * Builds the fixture.
     */
    private function build(): void {
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->roll = $gen->create_module('rollcall', ['course' => $this->course->id]);
        $this->g1 = $gen->create_group(['courseid' => $this->course->id, 'name' => 'G1']);
        $this->g2 = $gen->create_group(['courseid' => $this->course->id, 'name' => 'G2']);
        $this->teacher = $gen->create_and_enrol($this->course, 'teacher');
        $this->editing = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->manager = $gen->create_user();
        $gen->role_assign(
            $this->get_role_id('manager'),
            $this->manager->id,
            \context_system::instance()->id
        );
        $gen->create_group_member(['groupid' => $this->g1->id, 'userid' => $this->teacher->id]);
    }

    /**
     * Returns a role id by shortname.
     *
     * @param string $shortname
     * @return int
     */
    private function get_role_id(string $shortname): int {
        global $DB;
        return (int)$DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * The capability is granted to teachers, editing teachers and managers, not to students.
     */
    public function test_capability_archetypes(): void {
        $this->resetAfterTest();
        $this->build();
        $context = \context_module::instance($this->roll->cmid);
        foreach ([$this->teacher, $this->editing, $this->manager] as $user) {
            $this->assertTrue(has_capability('mod/rollcall:takeattendance', $context, $user));
        }
        $this->assertFalse(has_capability('mod/rollcall:takeattendance', $context, $this->student));
    }

    /**
     * Sets the group mode of the roll and returns a fresh cm_info.
     *
     * @param int $mode
     * @param int $groupingid
     * @return \cm_info
     */
    private function cm(int $mode, int $groupingid = 0): \cm_info {
        global $DB;
        $DB->update_record('course_modules', (object)[
            'id' => $this->roll->cmid,
            'groupmode' => $mode,
            'groupingid' => $groupingid,
        ]);
        rebuild_course_cache($this->course->id, true);
        return get_fast_modinfo($this->course->id)->get_cm($this->roll->cmid);
    }

    /**
     * Creates a session of a group.
     *
     * @param int $groupid
     * @return \mod_rollcall\persistent\session
     */
    private function session(int $groupid): \mod_rollcall\persistent\session {
        return session_manager::create((int)$this->roll->id, $groupid, 'S', time(), 3600);
    }

    /**
     * Tests the rules of can_take.
     */
    public function test_can_take_rules(): void {
        $this->resetAfterTest();
        $this->build();
        $s0 = $this->session(0);
        $s1 = $this->session((int)$this->g1->id);
        $s2 = $this->session((int)$this->g2->id);

        $cm = $this->cm(NOGROUPS);
        foreach ([$this->teacher, $this->editing, $this->manager] as $user) {
            $this->assertTrue(session_manager::can_take($cm, $s0, $user->id));
            $this->assertTrue(session_manager::can_take($cm, $s2, $user->id));
        }
        $this->assertFalse(session_manager::can_take($cm, $s0, $this->student->id));

        foreach ([SEPARATEGROUPS, VISIBLEGROUPS] as $mode) {
            $cm = $this->cm($mode);
            $this->assertTrue(session_manager::can_take($cm, $s1, $this->teacher->id));
            $this->assertFalse(session_manager::can_take($cm, $s2, $this->teacher->id));
            $this->assertFalse(session_manager::can_take($cm, $s0, $this->teacher->id));
            $this->assertFalse(session_manager::can_take($cm, $s1, $this->student->id));
            $this->assertTrue(session_manager::can_take($cm, $s1, $this->editing->id));
            foreach ([$s0, $s1, $s2] as $s) {
                $this->assertTrue(session_manager::can_take($cm, $s, $this->manager->id));
            }
        }
    }

    /**
     * Sessions of deleted groups can only be taken by users with access to all groups.
     */
    public function test_can_take_deleted_group(): void {
        $this->resetAfterTest();
        $this->build();
        $s2 = $this->session((int)$this->g2->id);
        groups_delete_group($this->g2->id);
        foreach ([NOGROUPS, SEPARATEGROUPS] as $mode) {
            $cm = $this->cm($mode);
            $this->assertFalse(session_manager::can_take($cm, $s2, $this->teacher->id));
            $this->assertTrue(session_manager::can_take($cm, $s2, $this->manager->id));
        }
    }

    /**
     * Managing sessions and taking attendance are independent capabilities.
     */
    public function test_capabilities_are_independent(): void {
        $this->resetAfterTest();
        $this->build();
        $s1 = $this->session((int)$this->g1->id);
        $cm = $this->cm(SEPARATEGROUPS);
        $context = \context_module::instance($this->roll->cmid);
        $roleid = $this->get_role_id('teacher');

        assign_capability('mod/rollcall:managesessions', CAP_PROHIBIT, $roleid, $context->id, true);
        $this->assertTrue(session_manager::can_take($cm, $s1, $this->teacher->id));
        $this->assertFalse(session_manager::can_manage($cm, (int)$this->g1->id, $this->teacher->id));

        assign_capability('mod/rollcall:managesessions', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('mod/rollcall:takeattendance', CAP_PROHIBIT, $roleid, $context->id, true);
        $this->assertFalse(session_manager::can_take($cm, $s1, $this->teacher->id));
        $this->assertTrue(session_manager::can_manage($cm, (int)$this->g1->id, $this->teacher->id));
    }

    /**
     * Tests the group used to query the roster.
     */
    public function test_roster_groupid(): void {
        $this->resetAfterTest();
        $this->build();
        $cm = $this->cm(SEPARATEGROUPS);
        $this->assertSame((int)$this->g1->id, session_manager::roster_groupid($cm, $this->session((int)$this->g1->id)));
        $this->assertSame(0, session_manager::roster_groupid($cm, $this->session(0)));
        $s2 = $this->session((int)$this->g2->id);
        groups_delete_group($this->g2->id);
        $this->assertSame(0, session_manager::roster_groupid($cm, $s2));
    }
}
