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

namespace mod_rollcall\form;

/**
 * Tests for the session form.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(session_form::class)]
final class session_form_test extends \advanced_testcase {
    /**
     * Builds the form with a posted submission.
     *
     * @param array $custom
     * @param array $post
     * @return session_form
     */
    private function submit(array $custom, array $post): session_form {
        $post += [
            'id' => 1,
            'sessionid' => 0,
            'name' => 'Session',
            'sessdate' => ['day' => 1, 'month' => 1, 'year' => 2026, 'hour' => 10, 'minute' => 0],
            'durationminutes' => '60',
        ];
        session_form::mock_submit($post);
        return new session_form('/mod/rollcall/editsession.php', $custom);
    }

    /**
     * Tells whether the form defines an element.
     *
     * @param session_form $form
     * @param string $name
     * @return bool
     */
    private function has_element(session_form $form, string $name): bool {
        $property = new \ReflectionProperty(\moodleform::class, '_form');
        return $property->getValue($form)->elementExists($name);
    }

    /**
     * Tests the duration and group validation, and the optional group element.
     */
    public function test_session_form_defaults_and_validation(): void {
        $this->resetAfterTest();
        $custom = ['groupoptions' => [], 'requiregroupchoice' => false];

        foreach (['0', '1441', 'abc', '1.5', '-5'] as $bad) {
            $form = $this->submit($custom, ['durationminutes' => $bad]);
            $this->assertNull($form->get_data(), "Duration $bad must be rejected");
        }
        foreach (['   ', '<b></b>'] as $blank) {
            $form = $this->submit($custom, ['name' => $blank]);
            $this->assertNull($form->get_data(), "Name '$blank' must be rejected");
        }
        foreach (['1', '1440'] as $good) {
            $form = $this->submit($custom, ['durationminutes' => $good]);
            $this->assertNotNull($form->get_data(), "Duration $good must be accepted");
        }
        $this->assertFalse($this->has_element($this->submit($custom, []), 'groupid'));

        $custom = ['groupoptions' => [5 => 'G5'], 'requiregroupchoice' => true];
        $form = $this->submit($custom, ['groupid' => '']);
        $this->assertNull($form->get_data());
        $this->assertTrue($this->has_element($form, 'groupid'));
        $form = $this->submit($custom, ['groupid' => '5']);
        $this->assertNotNull($form->get_data());
    }
}
