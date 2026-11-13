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
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/rollcall/mod_form.php');

/**
 * Tests for the activity settings form.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_rollcall_mod_form::class)]
final class mod_form_test extends \advanced_testcase {
    /**
     * Tests the form takes points or no grade, and refuses a scale.
     */
    public function test_validation_refuses_scales(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $scale = $this->getDataGenerator()->create_scale();
        $course = $this->getDataGenerator()->create_course();
        // The form reads the sections and the grade items of the current course.
        $PAGE->set_course($course);
        [, , , , $data] = prepare_new_moduleinfo_data($course, 'rollcall', 0);
        $form = new \mod_rollcall_mod_form($data, 0, null, $course);
        $submitted = [
            'name' => 'Roll',
            'cmidnumber' => '',
            'availabilityconditionsjson' => json_encode(\core_availability\tree::get_root_json([])),
        ] + (array)$data;

        $this->assertArrayNotHasKey('grade', $form->validation(['grade' => 0] + $submitted, []));
        $this->assertArrayNotHasKey('grade', $form->validation(['grade' => 10] + $submitted, []));
        $errors = $form->validation(['grade' => -(int)$scale->id] + $submitted, []);
        $this->assertSame(get_string('scalesnotsupported', 'mod_rollcall'), $errors['grade']);
    }
}
