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

use mod_rollcall\local\attendance_manager;
use mod_rollcall\local\report;
use mod_rollcall\local\session_manager;
use mod_rollcall\local\status_manager;
use mod_rollcall\local\student_summary;

/**
 * Tests for the attendance report calculation.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(report::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(student_summary::class)]
final class report_test extends \advanced_testcase {
    /** The current time used by every test. */
    private const NOW = 100000;

    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass */
    private \stdClass $roll;
    /** @var \stdClass */
    private \stdClass $a;
    /** @var \stdClass */
    private \stdClass $b;
    /** @var int[] Default status ids by name: present, late, absent */
    private array $status;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['lang' => 'en']);
        $this->roll = $gen->create_module('rollcall', ['course' => $this->course->id]);
        $this->a = $gen->create_and_enrol($this->course, 'student');
        $this->b = $gen->create_and_enrol($this->course, 'student');
        $ids = array_map(fn($s) => (int)$s->get('id'), array_values(status_manager::get_all((int)$this->roll->id)));
        $this->status = ['present' => $ids[0], 'late' => $ids[1], 'absent' => $ids[2]];
    }

    /**
     * Creates a session of the roll.
     *
     * @param int $sessdate
     * @param int $groupid
     * @param int $rollid Roll, or 0 for the default one
     * @return int Session id
     */
    private function session(int $sessdate, int $groupid = 0, int $rollid = 0): int {
        return (int)session_manager::create($rollid ?: (int)$this->roll->id, $groupid, 'S', $sessdate, 3600)->get('id');
    }

    /**
     * Calculates the summaries of the default students.
     *
     * @return student_summary[]
     */
    private function summarise(): array {
        $cm = get_fast_modinfo($this->course)->get_cm($this->roll->cmid);
        return report::summarise($cm, [(int)$this->a->id, (int)$this->b->id], self::NOW);
    }

    public function test_users_example(): void {
        $marks = ['present', 'present', 'late', 'present', 'present', 'present'];
        foreach ($marks as $i => $mark) {
            attendance_manager::set($this->session(1000 + $i), $this->status[$mark], [$this->a->id]);
        }
        $this->session(2000);
        for ($i = 0; $i < 3; $i++) {
            $this->session(self::NOW + 1000 + $i);
        }

        $a = $this->summarise()[$this->a->id];

        $this->assertSame(7, $a->total);
        $this->assertSame(1, $a->unmarked);
        $this->assertEquals([$this->status['present'] => 5, $this->status['late'] => 1], $a->counts);
        $this->assertSame(78.6, round($a->percent, 1));
    }

    public function test_no_past_sessions(): void {
        $this->session(self::NOW + 1);

        $summaries = $this->summarise();

        $this->assertSame(0, $summaries[$this->a->id]->total);
        $this->assertSame(0, $summaries[$this->a->id]->unmarked);
        $this->assertSame([], $summaries[$this->a->id]->counts);
        $this->assertNull($summaries[$this->a->id]->percent);
        $this->assertNull($summaries[$this->b->id]->percent);
    }

    public function test_session_starting_now_counts(): void {
        attendance_manager::set($this->session(self::NOW), $this->status['present'], [$this->a->id]);
        attendance_manager::set($this->session(self::NOW + 1), $this->status['absent'], [$this->a->id]);

        $a = $this->summarise()[$this->a->id];

        $this->assertSame(1, $a->total);
        $this->assertSame(100.0, $a->percent);
    }

    public function test_hidden_status_counts_with_its_weight(): void {
        attendance_manager::set($this->session(1000), $this->status['late'], [$this->a->id]);
        attendance_manager::set($this->session(2000), $this->status['late'], [$this->a->id]);
        status_manager::set_visible($this->status['late'], false);

        $a = $this->summarise()[$this->a->id];

        $this->assertSame(50.0, $a->percent);
        $this->assertEquals([$this->status['late'] => 2], $a->counts);
    }

    public function test_weight_change_applies_retroactively(): void {
        attendance_manager::set($this->session(1000), $this->status['late'], [$this->a->id]);
        $this->assertSame(50.0, $this->summarise()[$this->a->id]->percent);

        status_manager::update($this->status['late'], 'Late', 80);

        $this->assertSame(80.0, $this->summarise()[$this->a->id]->percent);
    }

    public function test_group_sessions(): void {
        $gen = $this->getDataGenerator();
        $g1 = $gen->create_group(['courseid' => $this->course->id]);
        $g2 = $gen->create_group(['courseid' => $this->course->id]);
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $this->a->id]);
        $gen->create_group_member(['groupid' => $g2->id, 'userid' => $this->b->id]);
        $this->session(1000);
        $s1 = $this->session(2000, (int)$g1->id);
        $s2 = $this->session(3000, (int)$g2->id);
        attendance_manager::set($s1, $this->status['present'], [$this->a->id]);
        attendance_manager::set($s2, $this->status['present'], [$this->b->id]);

        $summaries = $this->summarise();

        $this->assertSame(2, $summaries[$this->a->id]->total);
        $this->assertSame(2, $summaries[$this->b->id]->total);
        $this->assertSame(50.0, $summaries[$this->a->id]->percent);
        $this->assertSame(50.0, $summaries[$this->b->id]->percent);
    }

    public function test_record_in_left_group_is_ignored(): void {
        $gen = $this->getDataGenerator();
        $g1 = $gen->create_group(['courseid' => $this->course->id]);
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $this->a->id]);
        $this->session(1000);
        attendance_manager::set($this->session(2000, (int)$g1->id), $this->status['present'], [$this->a->id]);
        groups_remove_member($g1->id, $this->a->id);

        $a = $this->summarise()[$this->a->id];

        $this->assertSame(1, $a->total);
        $this->assertSame(1, $a->unmarked);
        $this->assertSame([], $a->counts);
        $this->assertSame(0.0, $a->percent);
    }

    public function test_deleted_group_session_counts_for_everyone(): void {
        $gen = $this->getDataGenerator();
        $g2 = $gen->create_group(['courseid' => $this->course->id]);
        $gen->create_group_member(['groupid' => $g2->id, 'userid' => $this->b->id]);
        attendance_manager::set($this->session(1000, (int)$g2->id), $this->status['present'], [$this->b->id]);
        groups_delete_group($g2->id);

        $summaries = $this->summarise();

        $this->assertSame(1, $summaries[$this->a->id]->total);
        $this->assertSame(1, $summaries[$this->a->id]->unmarked);
        $this->assertSame(1, $summaries[$this->b->id]->total);
        $this->assertSame(100.0, $summaries[$this->b->id]->percent);
    }

    public function test_records_of_other_rolls_are_ignored(): void {
        $other = $this->getDataGenerator()->create_module('rollcall', ['course' => $this->course->id]);
        $otherpresent = (int)array_values(status_manager::get_all((int)$other->id))[0]->get('id');
        attendance_manager::set($this->session(1000, 0, (int)$other->id), $otherpresent, [$this->a->id]);
        $this->session(2000);

        $a = $this->summarise()[$this->a->id];

        $this->assertSame(1, $a->total);
        $this->assertSame(1, $a->unmarked);
        $this->assertSame(0.0, $a->percent);
    }
}
