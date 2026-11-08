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
 * Tests for the take attendance page renderable.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(take_page::class)]
final class take_page_test extends \advanced_testcase {
    /** @var \cm_info */
    private \cm_info $cm;
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \mod_rollcall\persistent\session */
    private $session;
    /** @var \stdClass[] Students keyed by "first last". */
    private array $s = [];
    /** @var \mod_rollcall\persistent\status */
    private $statusa;
    /** @var \mod_rollcall\persistent\status */
    private $statush;
    /** @var \mod_rollcall\persistent\category */
    private $categoryc;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $DB->delete_records('rollcall_category');

        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $roll = $gen->create_module('rollcall', ['course' => $this->course->id]);
        $this->cm = get_fast_modinfo($this->course)->get_cm($roll->cmid);
        // Drop the default statuses so the fixture controls them.
        $DB->delete_records('rollcall_status', ['rollcallid' => $roll->id]);
        $this->statusa = status_manager::create((int)$roll->id, 'A');
        $this->statush = status_manager::create((int)$roll->id, 'H');
        $this->categoryc = $gen->get_plugin_generator('mod_rollcall')->create_category(['name' => 'C', 'color' => 'amber']);

        // Sorted by last name: Eve Alpha, Dan Beta, Bob Delta, Cid Gamma, Ann Zeta.
        $people = [['Eve', 'Alpha'], ['Dan', 'Beta'], ['Cid', 'Gamma'], ['Bob', 'Delta'], ['Ann', 'Zeta']];
        foreach ($people as [$first, $last]) {
            $user = $gen->create_user(['firstname' => $first, 'lastname' => $last]);
            $gen->enrol_user($user->id, $this->course->id, 'student');
            $this->s[$first] = $user;
        }
        classification::set(
            (int)$this->course->id,
            [(int)$this->s['Dan']->id, (int)$this->s['Ann']->id],
            (int)$this->categoryc->get('id'),
            $this->cm->context
        );
        $this->session = session_manager::create((int)$roll->id, 0, 'Class', 1000, 3600);
    }

    /**
     * Returns the exported data of a page.
     *
     * @param array $args Overrides: filter, sort, dir, page, perpage
     * @return array
     */
    private function export(array $args = []): array {
        global $PAGE;
        $args += ['filter' => 'all', 'sort' => 'lastname', 'dir' => 'asc', 'page' => 0, 'perpage' => 2];
        $page = new take_page($this->cm, $this->session, $args['filter'], $args['sort'], $args['dir'], $args['page'], $args['perpage']);
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
     * Tests the rows, filter, sort and paging.
     */
    public function test_rows_filter_sort_and_paging(): void {
        $this->assertSame(['Eve', 'Dan'], $this->names($this->export()));
        $this->assertSame(['Ann', 'Cid'], $this->names($this->export(['dir' => 'desc'])));
        $this->assertSame(['Ann', 'Bob'], $this->names($this->export(['sort' => 'firstname'])));

        $filtered = $this->export(['filter' => (string)$this->categoryc->get('id')]);
        $this->assertSame(['Dan', 'Ann'], $this->names($filtered));
        $this->assertTrue($filtered['rows'][0]['hascategory']);
        $this->assertSame('C', $filtered['rows'][0]['categoryname']);
        $this->assertSame('amber', $filtered['rows'][0]['color']);
        $this->assertStringNotContainsString('page=', $filtered['pagingbar']);

        $last = $this->export(['page' => 99]);
        $this->assertCount(1, $last['rows']);
        $this->assertSame(['Ann'], $this->names($last));
        $this->assertStringContainsString('filter=', $last['pagingbar']);
        $this->assertStringContainsString('sort=', $last['pagingbar']);

        $this->assertSame(['all', 'regular', (string)$this->categoryc->get('id')], array_column($last['filters'], 'value'));
        $this->assertSame((int)$this->session->get('id'), $last['sessionid']);
        $this->assertTrue($last['hasstatuses']);
        $this->assertFalse($last['empty']);
    }

    /**
     * Tests the link to manage statuses depends on the capability.
     */
    public function test_manage_statuses_url(): void {
        $gen = $this->getDataGenerator();
        $editing = $gen->create_and_enrol($this->course, 'editingteacher');
        $teacher = $gen->create_and_enrol($this->course, 'teacher');
        $this->setUser($editing);
        $this->assertNotNull($this->export()['managestatusesurl']);
        $this->setUser($teacher);
        $this->assertNull($this->export()['managestatusesurl']);
    }

    /**
     * Tests hidden statuses, the no status option, escaping and the empty states.
     */
    public function test_hidden_status_and_escaping(): void {
        global $DB, $PAGE;
        $u1 = $this->s['Eve'];
        attendance_manager::set((int)$this->session->get('id'), (int)$this->statush->get('id'), [(int)$u1->id]);
        status_manager::set_visible((int)$this->statush->get('id'), false);

        $data = $this->export(['perpage' => 10]);
        $hid = (int)$this->statush->get('id');
        $rowu1 = $data['rows'][0];
        $this->assertSame((int)$u1->id, $rowu1['userid']);
        $this->assertSame($hid, $rowu1['statusid']);
        $byid = array_column($rowu1['options'], null, 'id');
        $this->assertSame(get_string('statushidden', 'mod_rollcall', 'H'), $byid[$hid]['name']);
        $this->assertTrue($byid[$hid]['selected']);
        $this->assertSame(0, $rowu1['options'][0]['id']);
        $this->assertFalse($rowu1['options'][0]['selected']);
        foreach (array_slice($data['rows'], 1) as $row) {
            $this->assertNotContains($hid, array_column($row['options'], 'id'));
            $this->assertSame(0, $row['options'][0]['id']);
            $this->assertTrue($row['options'][0]['selected']);
            $this->assertSame(0, $row['statusid']);
        }
        $this->assertSame(0, $data['bulkstatuses'][0]['id']);
        $this->assertNotContains($hid, array_column($data['bulkstatuses'], 'id'));
        $this->assertCount(2, $data['bulkstatuses']);

        // The session and status names are escaped once.
        $DB->set_field('rollcall_session', 'name', 'A & <b>B</b>', ['id' => $this->session->get('id')]);
        $DB->set_field('rollcall_status', 'name', 'X & Y', ['id' => $this->statusa->get('id')]);
        $this->session = session_manager::get_for_rollcall((int)$this->session->get('id'), (int)$this->cm->instance);
        $data = $this->export(['perpage' => 10]);
        $this->assertSame('A &amp; B', $data['sessionname']);
        $this->assertSame('X &amp; Y', $data['bulkstatuses'][1]['name']);
        $html = $PAGE->get_renderer('core')->render(new take_page($this->cm, $this->session, 'all', 'lastname', 'asc', 0, 10));
        $this->assertStringNotContainsString('<b>B</b>', $html);
        $this->assertStringContainsString('X &amp; Y', $html);
        $this->assertStringContainsString('data-region="mod_rollcall-take"', $html);
        $this->assertDebuggingNotCalled();

        status_manager::set_visible((int)$this->statusa->get('id'), false);
        $this->assertFalse($this->export()['hasstatuses']);
    }
}
