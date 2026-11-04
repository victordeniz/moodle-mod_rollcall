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

use mod_rollcall\local\category_manager;
use mod_rollcall\local\classification;
use mod_rollcall\persistent\category;

/**
 * Tests for the roll page renderable.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(roll_page::class)]
final class roll_page_test extends \advanced_testcase {
    /** @var \cm_info */
    private \cm_info $cm;
    /** @var category */
    private category $repeater;
    /** @var category */
    private category $erasmus;
    /** @var \stdClass[] Students: Alpha (repeater), Beta (Erasmus), Gamma (regular). */
    private array $s = [];

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $DB->delete_records('rollcall_category'); // Start without the installed defaults.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $this->cm = get_fast_modinfo($course)->get_cm($roll->cmid);
        $this->repeater = $gen->get_plugin_generator('mod_rollcall')->create_category(['name' => 'Repeater', 'color' => 'amber']);
        $this->erasmus = $gen->get_plugin_generator('mod_rollcall')->create_category(['name' => 'Erasmus', 'color' => 'blue']);

        foreach (['Gamma', 'Alpha', 'Beta'] as $lastname) {
            $user = $gen->create_user(['firstname' => 'Ana', 'lastname' => $lastname]);
            $gen->enrol_user($user->id, $course->id, 'student');
            $this->s[$lastname] = $user;
        }
        classification::set($course->id, [$this->s['Alpha']->id], $this->repeater->get('id'), $this->cm->context);
        classification::set($course->id, [$this->s['Beta']->id], $this->erasmus->get('id'), $this->cm->context);
    }

    /**
     * Exports the page for the given filter.
     *
     * @param string $filter
     * @param bool $canclassify
     * @return array
     */
    private function export(string $filter = 'all', bool $canclassify = false): array {
        global $PAGE;
        return (new roll_page($this->cm, 0, $filter, $canclassify))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Legend as name => count.
     *
     * @param array $data
     * @return array
     */
    private function legend(array $data): array {
        return array_column($data['legend'], 'count', 'name');
    }

    public function test_rows_and_legend(): void {
        $data = $this->export();

        $this->assertSame(['Alpha', 'Beta', 'Gamma'], array_map(fn($r) => explode(' ', $r['fullname'])[1], $data['rows']));
        $this->assertSame(['amber', 'blue', ''], array_column($data['rows'], 'color'));
        $this->assertSame([true, true, false], array_column($data['rows'], 'hascategory'));
        $this->assertSame(['Repeater' => 1, 'Erasmus' => 1, get_string('regular', 'mod_rollcall') => 1], $this->legend($data));
        $this->assertFalse($data['canclassify']);
        $this->assertArrayNotHasKey('options', $data['rows'][0]);
        $this->assertFalse($data['empty']);
        $this->assertSame((int)$this->cm->id, $data['cmid']);
    }

    public function test_options_when_classifying(): void {
        $data = $this->export('all', true);

        $options = $data['rows'][1]['options']; // Beta, Erasmus.
        $this->assertSame([0, (int)$this->repeater->get('id'), (int)$this->erasmus->get('id')], array_column($options, 'id'));
        $this->assertSame([false, false, true], array_column($options, 'selected'));
        $this->assertSame(['', 'amber', 'blue'], array_column($options, 'color'));
    }

    public function test_filter_by_category(): void {
        $data = $this->export((string)$this->erasmus->get('id'));

        $this->assertSame([(int)$this->s['Beta']->id], array_column($data['rows'], 'userid'));
        $this->assertSame(['Repeater' => 1, 'Erasmus' => 1, get_string('regular', 'mod_rollcall') => 1], $this->legend($data));
        $selected = array_values(array_filter($data['filters'], fn($f) => $f['selected']));
        $this->assertSame((string)$this->erasmus->get('id'), $selected[0]['value']);
    }

    public function test_regular_filter_includes_hidden_category_users(): void {
        category_manager::set_visible($this->repeater->get('id'), false);

        $data = $this->export('regular');

        $this->assertSame([(int)$this->s['Alpha']->id, (int)$this->s['Gamma']->id], array_column($data['rows'], 'userid'));
        $this->assertSame(['Erasmus' => 1, get_string('regular', 'mod_rollcall') => 2], $this->legend($data));
    }

    public function test_unknown_filter_shows_all(): void {
        $data = $this->export('999999');
        $this->assertCount(3, $data['rows']);
    }

    public function test_category_name_is_formatted(): void {
        global $DB;
        // The persistent refuses HTML names, so store one directly as if it came from elsewhere.
        $DB->set_field('rollcall_category', 'name', '<b>X</b><script>alert(1)</script>', ['id' => $this->erasmus->get('id')]);

        $data = $this->export();

        $this->assertStringNotContainsString('<script>', $data['rows'][1]['categoryname']);
        $this->assertStringNotContainsString('<script>', json_encode($data['legend']));
    }

    public function test_empty(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($roll->cmid);

        $data = (new roll_page($cm, 0, 'all', false))->export_for_template($GLOBALS['PAGE']->get_renderer('core'));

        $this->assertTrue($data['empty']);
        $this->assertSame([], $data['rows']);
    }
}
