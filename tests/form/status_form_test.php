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
 * Tests for the status form.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(status_form::class)]
final class status_form_test extends \advanced_testcase {
    /**
     * Weight values and whether the form accepts them.
     *
     * @return array
     */
    public static function weight_provider(): array {
        return [
            'zero' => ['0', true],
            'hundred' => ['100', true],
            'fifty' => ['50', true],
            'empty' => ['', false],
            'negative' => ['-1', false],
            'over a hundred' => ['101', false],
            'decimal' => ['50.5', false],
            'text' => ['abc', false],
        ];
    }

    /**
     * Tests the weight must be a whole number from 0 to 100.
     *
     * @param string $weight
     * @param bool $valid
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('weight_provider')]
    public function test_weight_validation(string $weight, bool $valid): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $roll = $this->getDataGenerator()->create_module('rollcall', ['course' => $course->id]);

        $form = new status_form(null, ['rollcallid' => (int)$roll->id]);
        $errors = $form->validation(['statusid' => 0, 'name' => 'New', 'weight' => $weight], []);

        if ($valid) {
            $this->assertArrayNotHasKey('weight', $errors);
        } else {
            $this->assertSame(get_string('weightrange', 'mod_rollcall'), $errors['weight'] ?? null);
        }
    }
}
