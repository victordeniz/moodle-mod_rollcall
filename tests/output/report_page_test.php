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

use mod_rollcall\local\attendance_manager;
use mod_rollcall\local\classification;
use mod_rollcall\local\session_manager;
use mod_rollcall\local\status_manager;

/**
 * Tests for the attendance report page renderable.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(report_page::class)]
final class report_page_test extends \advanced_testcase {
    /** The current time used by every test. */
    private const NOW = 100000;

    /** @var \stdClass */
    private \stdClass $course;
    /** @var \cm_info */
    private \cm_info $cm;
    /** @var int[] Default status ids: present, late, absent */
    private array $status;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $DB->delete_records('rollcall_category');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['lang' => 'en']);
        $roll = $gen->create_module('rollcall', ['course' => $this->course->id]);
        $this->cm = get_fast_modinfo($this->course)->get_cm($roll->cmid);
        $ids = array_map(fn($s) => (int)$s->get('id'), array_values(status_manager::get_all((int)$roll->id)));
        $this->status = ['present' => $ids[0], 'late' => $ids[1], 'absent' => $ids[2]];
    }

    /**
     * Enrols a student.
     *
     * @param string $first
     * @param string $last
     * @return int User id
     */
    private function student(string $first, string $last): int {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user(['firstname' => $first, 'lastname' => $last]);
        $gen->enrol_user($user->id, $this->course->id, 'student');
        return (int)$user->id;
    }

    /**
     * Creates a session of the roll.
     *
     * @param int $sessdate
     * @param int $groupid
     * @return int
     */
    private function session(int $sessdate, int $groupid = 0): int {
        return (int)session_manager::create((int)$this->cm->instance, $groupid, 'S', $sessdate, 3600)->get('id');
    }

    /**
     * Returns the exported data of a page.
     *
     * @param array $args Overrides: groupid, filter, sort, dir, page, perpage
     * @return array
     */
    private function export(array $args = []): array {
        global $PAGE;
        $args += ['groupid' => 0, 'filter' => 'all', 'sort' => 'lastname', 'dir' => 'asc', 'page' => 0, 'perpage' => 2];
        $page = new report_page($this->cm, $args['groupid'], $args['filter'], $args['sort'], $args['dir'], $args['page'],
            $args['perpage'], self::NOW);
        return $page->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Returns the first names of the rows, in order.
     *
     * @param array $data
     * @return string[]
     */
    private function names(array $data): array {
        return array_map(fn($row) => explode(' ', $row['fullname'])[0], $data['rows']);
    }

    /**
     * Formats a percentage like the page.
     *
     * @param float $value
     * @return string
     */
    private function percent(float $value): string {
        return get_string('percentvalue', 'mod_rollcall', format_float($value, 1));
    }

    public function test_columns(): void {
        $eve = $this->student('Eve', 'Alpha');
        $session = $this->session(1000);
        status_manager::set_visible($this->status['late'], false);

        $this->assertSame(['Present (100)', 'Absent (0)'], array_column($this->export()['columns'], 'name'));

        attendance_manager::set($session, $this->status['late'], [$eve]);
        $columns = $this->export()['columns'];

        $this->assertSame(['Present (100)', 'Late (50) (hidden)', 'Absent (0)'], array_column($columns, 'name'));
        $this->assertSame([false, true, false], array_column($columns, 'hidden'));
    }

    public function test_rows_counts_and_warning(): void {
        $eve = $this->student('Eve', 'Alpha');
        $dan = $this->student('Dan', 'Beta');
        $s1 = $this->session(1000);
        $s2 = $this->session(2000);
        attendance_manager::set($s1, $this->status['present'], [$eve, $dan]);
        attendance_manager::set($s2, $this->status['present'], [$eve]);

        $data = $this->export();

        [$everow, $danrow] = $data['rows'];
        $this->assertSame([2, 0, 0], array_column($everow['counts'], 'value'));
        $this->assertSame(0, $everow['unmarked']);
        $this->assertFalse($everow['haswarning']);
        $this->assertSame($this->percent(100), $everow['percent']);
        $this->assertSame([1, 0, 0], array_column($danrow['counts'], 'value'));
        $this->assertSame(1, $danrow['unmarked']);
        $this->assertTrue($danrow['haswarning']);
        $this->assertSame($this->percent(50), $danrow['percent']);
        $this->assertFalse($data['nopastsessions']);
        $this->assertFalse($data['empty']);
    }

    public function test_no_past_sessions(): void {
        $this->student('Eve', 'Alpha');
        $this->student('Dan', 'Beta');
        $this->session(self::NOW + 1);

        $data = $this->export();

        $this->assertTrue($data['nopastsessions']);
        $this->assertSame(['—', '—'], array_column($data['rows'], 'percent'));
    }

    public function test_sort_by_percent(): void {
        $gen = $this->getDataGenerator();
        $g1 = $gen->create_group(['courseid' => $this->course->id]);
        $g2 = $gen->create_group(['courseid' => $this->course->id]);
        $p = $this->student('P', 'Alpha');
        $q = $this->student('Q', 'Gamma');
        $r = $this->student('R', 'Beta');
        $s = $this->student('S', 'Delta');
        foreach ([$p, $q, $r] as $userid) {
            $gen->create_group_member(['groupid' => $g1->id, 'userid' => $userid]);
        }
        $gen->create_group_member(['groupid' => $g2->id, 'userid' => $s]);
        $s1 = $this->session(1000, (int)$g1->id);
        $s2 = $this->session(2000, (int)$g1->id);
        $this->session(self::NOW + 1, (int)$g2->id);
        attendance_manager::set($s1, $this->status['present'], [$p, $q, $r]);
        attendance_manager::set($s2, $this->status['present'], [$p]);
        attendance_manager::set($s2, $this->status['absent'], [$q, $r]);

        $asc = $this->export(['sort' => 'percent', 'perpage' => 10]);
        $desc = $this->export(['sort' => 'percent', 'dir' => 'desc', 'perpage' => 10]);

        $this->assertSame(['R', 'Q', 'P', 'S'], $this->names($asc));
        $this->assertSame(['P', 'R', 'Q', 'S'], $this->names($desc));
        $this->assertSame(['firstname', 'lastname', 'percent'], array_column($asc['sortlinks'], 'key'));
        $this->assertTrue($asc['sortlinks'][2]['active']);
    }

    public function test_filter_and_paging(): void {
        $category = $this->getDataGenerator()->get_plugin_generator('mod_rollcall')->create_category(['name' => 'C']);
        $eve = $this->student('Eve', 'Alpha');
        $dan = $this->student('Dan', 'Beta');
        $cid = $this->student('Cid', 'Gamma');
        $this->student('Bob', 'Delta');
        classification::set((int)$this->course->id, [$eve, $dan, $cid], (int)$category->get('id'), $this->cm->context);
        $this->session(1000);

        $filter = (string)$category->get('id');
        $last = $this->export(['filter' => $filter, 'page' => 99]);

        $this->assertSame(['Cid'], $this->names($last));
        $this->assertStringContainsString('filter=', $last['pagingbar']);
        $this->assertStringContainsString('sort=', $last['pagingbar']);
        $this->assertSame(['all', 'regular', $filter], array_column($last['filters'], 'value'));
        $this->assertSame(['Bob'], $this->names($this->export(['filter' => 'regular'])));
    }

    public function test_group_restricts_rows(): void {
        $gen = $this->getDataGenerator();
        $g1 = $gen->create_group(['courseid' => $this->course->id]);
        $eve = $this->student('Eve', 'Alpha');
        $this->student('Dan', 'Beta');
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $eve]);

        $this->assertSame(['Eve'], $this->names($this->export(['groupid' => (int)$g1->id])));
    }

    public function test_escaping(): void {
        $this->student('Eve', 'Alpha');
        // Status names cannot hold tags (PARAM_TEXT), but special characters must still be escaped.
        status_manager::create((int)$this->cm->instance, 'Tom & Jerry', 10);

        $names = array_column($this->export()['columns'], 'name');

        $this->assertCount(4, $names);
        $this->assertSame('Tom &amp; Jerry (10)', $names[3]);
    }

    public function test_no_students(): void {
        $this->session(1000);

        $this->assertTrue($this->export()['empty']);
    }
}
