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

use mod_rollcall\local\classification;
use mod_rollcall\local\roster;

/**
 * Tests for the list of students shown in the roll.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(roster::class)]
final class roster_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \cm_info */
    private \cm_info $cm;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $roll = $this->getDataGenerator()->create_module('rollcall', ['course' => $this->course->id]);
        $this->cm = get_fast_modinfo($this->course)->get_cm($roll->cmid);
    }

    /**
     * Creates and enrols a user with the given names.
     *
     * @param string $firstname
     * @param string $lastname
     * @param string $role
     * @param string $status
     * @return \stdClass
     */
    private function enrol(string $firstname, string $lastname, string $role = 'student', string $status = 'active'): \stdClass {
        $user = $this->getDataGenerator()->create_user(['firstname' => $firstname, 'lastname' => $lastname]);
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $role, 'manual', 0, 0,
            $status === 'active' ? ENROL_USER_ACTIVE : ENROL_USER_SUSPENDED);
        return $user;
    }

    public function test_roster_lists_students_sorted(): void {
        $zapata = $this->enrol('Ana', 'Zapata');
        $alvarez = $this->enrol('Luis', 'Álvarez');
        $benitez = $this->enrol('Eva', 'Benítez');
        $this->enrol('Tere', 'Aaron', 'editingteacher');

        $students = roster::get_students($this->cm, 0);

        $this->assertSame([(int)$alvarez->id, (int)$benitez->id, (int)$zapata->id], array_map('intval', array_keys($students)));
        $first = reset($students);
        $this->assertSame('Álvarez', $first->lastname);
        $this->assertObjectHasProperty('picture', $first);
    }

    public function test_roster_excludes_suspended_and_non_students(): void {
        global $DB;
        $active = $this->enrol('Ana', 'Activa');
        $suspended = $this->enrol('Sara', 'Suspendida', 'student', 'suspended');
        $exstudent = $this->enrol('Exa', 'Exalumna');
        $category = $this->getDataGenerator()->get_plugin_generator('mod_rollcall')->create_category();
        classification::set($this->course->id, [$suspended->id, $exstudent->id], $category->get('id'), $this->cm->context);

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        role_unassign($studentrole, $exstudent->id, \context_course::instance($this->course->id)->id);

        $this->assertSame([(int)$active->id], array_map('intval', array_keys(roster::get_students($this->cm, 0))));
        $this->assertFalse(roster::is_listed($this->cm, $suspended->id, 0));
        $this->assertFalse(roster::is_listed($this->cm, $exstudent->id, 0));
        $this->assertTrue(roster::is_listed($this->cm, $active->id, 0));
        // Their classifications are kept.
        $this->assertSame(2, $DB->count_records('rollcall_usercat', ['courseid' => $this->course->id]));
    }

    public function test_roster_filters_by_group(): void {
        $s1 = $this->enrol('Uno', 'Primero');
        $s2 = $this->enrol('Dos', 'Segundo');
        $g1 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $g2 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $g1->id, 'userid' => $s1->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $g2->id, 'userid' => $s2->id]);

        $this->assertSame([(int)$s1->id], array_map('intval', array_keys(roster::get_students($this->cm, $g1->id))));
        $this->assertTrue(roster::is_listed($this->cm, $s1->id, $g1->id));
        $this->assertFalse(roster::is_listed($this->cm, $s2->id, $g1->id));
    }
}
