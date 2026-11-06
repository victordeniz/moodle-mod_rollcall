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
 * Tests for the session capability and the group permission rules.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(session_manager::class)]
final class session_permissions_test extends \advanced_testcase {
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
     * Tests the rules of can_manage.
     */
    public function test_can_manage_rules(): void {
        $this->resetAfterTest();
        $this->build();
        $other = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_group(['courseid' => $other->id]);

        $cm = $this->cm(SEPARATEGROUPS);
        $t = $this->teacher->id;
        $this->assertTrue(session_manager::can_manage($cm, $this->g1->id, $t));
        $this->assertFalse(session_manager::can_manage($cm, $this->g2->id, $t));
        $this->assertFalse(session_manager::can_manage($cm, 0, $t));
        $this->assertFalse(session_manager::can_manage($cm, $foreign->id, $t));

        $m = $this->manager->id;
        $this->assertTrue(session_manager::can_manage($cm, $this->g1->id, $m));
        $this->assertTrue(session_manager::can_manage($cm, $this->g2->id, $m));
        $this->assertTrue(session_manager::can_manage($cm, 0, $m));
        $this->assertFalse(session_manager::can_manage($cm, $foreign->id, $m));

        $s = $this->student->id;
        $this->assertFalse(session_manager::can_manage($cm, 0, $s));
        $this->assertFalse(session_manager::can_manage($cm, $this->g1->id, $s));

        $cm = $this->cm(VISIBLEGROUPS);
        $this->assertFalse(session_manager::can_manage($cm, $this->g2->id, $t));

        $cm = $this->cm(NOGROUPS);
        $this->assertTrue(session_manager::can_manage($cm, 0, $t));
        $this->assertTrue(session_manager::can_manage($cm, $this->g2->id, $t));

        groups_delete_group($this->g1->id);
        foreach ([SEPARATEGROUPS, VISIBLEGROUPS, NOGROUPS] as $mode) {
            $cm = $this->cm($mode);
            $this->assertFalse(session_manager::can_manage($cm, $this->g1->id, $t));
            $this->assertTrue(session_manager::can_manage($cm, $this->g1->id, $m));
        }
    }

    /**
     * Tests the group options of the session form.
     */
    public function test_group_options(): void {
        $this->resetAfterTest();
        $this->build();

        $cm = $this->cm(SEPARATEGROUPS);
        $this->assertSame([(int)$this->g1->id], array_keys(session_manager::group_options($cm, $this->teacher->id)));

        $options = session_manager::group_options($cm, $this->manager->id);
        $this->assertSame([0, (int)$this->g1->id, (int)$this->g2->id], array_keys($options));

        $cm = $this->cm(NOGROUPS);
        $this->assertSame([], session_manager::group_options($cm, $this->manager->id));

        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $this->g2->id]);
        $cm = $this->cm(SEPARATEGROUPS, $grouping->id);
        $this->assertSame([0, (int)$this->g2->id], array_keys(session_manager::group_options($cm, $this->manager->id)));
    }

    /**
     * Tests can_create.
     */
    public function test_can_create(): void {
        $this->resetAfterTest();
        $this->build();
        $cm = $this->cm(SEPARATEGROUPS);
        $this->assertTrue(session_manager::can_create($cm, $this->teacher->id));
        $this->assertFalse(session_manager::can_create($cm, $this->student->id));
        // The editing teacher has the capability but no group and no accessallgroups... it has accessallgroups.
        $this->assertTrue(session_manager::can_create($cm, $this->editing->id));
        $cm = $this->cm(NOGROUPS);
        $this->assertTrue(session_manager::can_create($cm, $this->teacher->id));
    }

    /**
     * Tests the visible sessions.
     */
    public function test_get_visible(): void {
        $this->resetAfterTest();
        $this->build();
        $cm = $this->cm(SEPARATEGROUPS);
        $id = (int)$this->roll->id;
        $s0 = session_manager::create($id, 0, 'S0', 1000, 60);
        $s1 = session_manager::create($id, (int)$this->g1->id, 'S1', 2000, 60);
        $s2 = session_manager::create($id, (int)$this->g2->id, 'S2', 3000, 60);

        $ids = fn(array $list) => array_map(fn($s) => (int)$s->get('id'), array_values($list));

        $visible = session_manager::get_visible($cm, (int)$this->g1->id, $this->teacher->id);
        $this->assertSame([(int)$s1->get('id'), (int)$s0->get('id')], $ids($visible));

        $visible = session_manager::get_visible($cm, 0, $this->manager->id);
        $this->assertSame([(int)$s2->get('id'), (int)$s1->get('id'), (int)$s0->get('id')], $ids($visible));

        groups_delete_group($this->g2->id);
        $cm = $this->cm(NOGROUPS);
        $visible = session_manager::get_visible($cm, 0, $this->teacher->id);
        $this->assertSame([(int)$s1->get('id'), (int)$s0->get('id')], $ids($visible));
        $visible = session_manager::get_visible($cm, 0, $this->manager->id);
        $this->assertSame([(int)$s2->get('id'), (int)$s1->get('id'), (int)$s0->get('id')], $ids($visible));
    }

    /**
     * Tests the archetypes of the capability.
     */
    public function test_capability_archetypes(): void {
        $this->resetAfterTest();
        $this->build();
        $context = \context_module::instance($this->roll->cmid);
        $cap = 'mod/rollcall:managesessions';
        $this->assertTrue(has_capability($cap, $context, $this->teacher->id));
        $this->assertTrue(has_capability($cap, $context, $this->editing->id));
        $this->assertTrue(has_capability($cap, $context, $this->manager->id));
        $this->assertFalse(has_capability($cap, $context, $this->student->id));
    }
}
