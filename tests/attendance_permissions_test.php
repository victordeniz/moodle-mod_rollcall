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
}
