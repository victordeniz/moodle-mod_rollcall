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

use mod_rollcall\local\status_manager;

/**
 * Tests for the access rules of the statuses page.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(status_manager::class)]
final class status_page_test extends \advanced_testcase {
    public function test_get_for_rollcall_rejects_status_of_other_activity(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $first = $gen->create_module('rollcall', ['course' => $gen->create_course()->id]);
        $second = $gen->create_module('rollcall', ['course' => $gen->create_course()->id]);
        $own = array_values(status_manager::get_all((int)$first->id))[0];

        $found = status_manager::get_for_rollcall((int)$own->get('id'), (int)$first->id);
        $this->assertEquals($own->get('id'), $found->get('id'));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidstatus', 'mod_rollcall'));
        status_manager::get_for_rollcall((int)$own->get('id'), (int)$second->id);
    }

    public function test_manage_capability_editingteacher_yes_student_no(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $context = \context_module::instance($roll->cmid);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');

        $this->assertTrue(has_capability('mod/rollcall:managestatuses', $context, $teacher));
        $this->assertFalse(has_capability('mod/rollcall:managestatuses', $context, $student));
    }

    public function test_form_validation_does_not_reveal_statuses_of_other_activity(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $mine = $gen->create_module('rollcall', ['course' => $gen->create_course()->id]);
        $other = $gen->create_module('rollcall', ['course' => $gen->create_course()->id]);
        $foreign = array_values(status_manager::get_all((int)$other->id))[0];
        $takenname = array_values(status_manager::get_all((int)$other->id))[1]->get('name');

        // Posting the id of a status of another activity must not validate against that activity's names.
        $form = new \mod_rollcall\form\status_form(null, ['rollcallid' => (int)$mine->id]);
        $errors = $form->validation(['statusid' => (int)$foreign->get('id'), 'name' => $takenname], []);

        $this->assertSame(get_string('invalidstatus', 'mod_rollcall'), $errors['name'] ?? null);
    }
}
