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

namespace mod_rollcall\external;

use core_external\external_api;
use mod_rollcall\local\session_manager;
use mod_rollcall\local\status_manager;

/**
 * Tests for the set_attendance external function.
 *
 * @package    mod_rollcall
 * @category   test
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(set_attendance::class)]
final class set_attendance_test extends \core_external\tests\externallib_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass */
    private \stdClass $roll;
    /** @var \stdClass */
    private \stdClass $g1;
    /** @var \stdClass */
    private \stdClass $g2;
    /** @var \stdClass Non-editing teacher in G1. */
    private \stdClass $teacher;
    /** @var \stdClass */
    private \stdClass $student;
    /** @var \stdClass */
    private \stdClass $u1;
    /** @var \stdClass */
    private \stdClass $u2;
    /** @var \stdClass Student of G2. */
    private \stdClass $other;
    /** @var \mod_rollcall\persistent\status */
    private $status;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->roll = $gen->create_module('rollcall', ['course' => $this->course->id, 'groupmode' => SEPARATEGROUPS]);
        $this->g1 = $gen->create_group(['courseid' => $this->course->id]);
        $this->g2 = $gen->create_group(['courseid' => $this->course->id]);
        $this->teacher = $gen->create_and_enrol($this->course, 'teacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->u1 = $gen->create_and_enrol($this->course, 'student');
        $this->u2 = $gen->create_and_enrol($this->course, 'student');
        $this->other = $gen->create_and_enrol($this->course, 'student');
        $gen->create_group_member(['groupid' => $this->g1->id, 'userid' => $this->teacher->id]);
        $gen->create_group_member(['groupid' => $this->g1->id, 'userid' => $this->u1->id]);
        $gen->create_group_member(['groupid' => $this->g1->id, 'userid' => $this->u2->id]);
        $gen->create_group_member(['groupid' => $this->g2->id, 'userid' => $this->other->id]);
        $this->status = status_manager::create((int)$this->roll->id, 'Custom', 0);
    }

    /**
     * Creates a session of a group in a roll.
     *
     * @param int $rollid
     * @param int $groupid
     * @return int
     */
    private function session(int $rollid, int $groupid): int {
        return (int)session_manager::create($rollid, $groupid, 'S', time(), 3600)->get('id');
    }

    /**
     * Calls the external function the way the web service layer does.
     *
     * @param int $sessionid
     * @param int $statusid
     * @param int[] $userids
     * @return array
     */
    private function call(int $sessionid, int $statusid, array $userids): array {
        $result = set_attendance::execute($this->roll->cmid, $sessionid, $statusid, $userids);
        return external_api::clean_returnvalue(set_attendance::execute_returns(), $result);
    }

    public function test_sets_status_and_returns_value(): void {
        global $DB;
        $sid = $this->session((int)$this->roll->id, (int)$this->g1->id);
        $stid = (int)$this->status->get('id');
        $this->setUser($this->teacher);

        $result = $this->call($sid, $stid, [$this->u1->id, $this->u2->id]);

        $this->assertSame([
            ['userid' => (int)$this->u1->id, 'statusid' => $stid],
            ['userid' => (int)$this->u2->id, 'statusid' => $stid],
        ], $result);
        $this->assertSame(2, $DB->count_records('rollcall_attendance', ['sessionid' => $sid, 'statusid' => $stid]));

        $result = $this->call($sid, 0, [$this->u1->id, $this->u2->id]);

        $this->assertSame([
            ['userid' => (int)$this->u1->id, 'statusid' => 0],
            ['userid' => (int)$this->u2->id, 'statusid' => 0],
        ], $result);
        $this->assertSame(0, $DB->count_records('rollcall_attendance'));
    }

    /**
     * Each rejected call raises the expected exception and leaves no attendance.
     *
     * @param string $case
     * @param string $exception
     * @param string|null $errorcode
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejections_provider')]
    public function test_rejections(string $case, string $exception, ?string $errorcode): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $sid = $this->session((int)$this->roll->id, (int)$this->g1->id);
        $stid = (int)$this->status->get('id');
        $userids = [$this->u1->id];
        $this->setUser($this->teacher);

        switch ($case) {
            case 'student':
                // Students cannot even view the roll; grant it so the take attendance check is what rejects them.
                $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
                $modcontextid = \context_module::instance($this->roll->cmid)->id;
                assign_capability('mod/rollcall:view', CAP_ALLOW, $studentrole, $modcontextid, true);
                $this->setUser($this->student);
                break;
            case 'othergroupsession':
                $sid = $this->session((int)$this->roll->id, (int)$this->g2->id);
                break;
            case 'usernotlisted':
                $userids = [$this->u1->id, $this->other->id];
                break;
            case 'negativestatus':
                $stid = -1;
                break;
            case 'hiddenstatus':
                status_manager::set_visible($stid, false);
                break;
            case 'otherrollstatus':
                $roll2 = $gen->create_module('rollcall', ['course' => $this->course->id]);
                $stid = (int)status_manager::create((int)$roll2->id, 'Foreign', 0)->get('id');
                break;
            case 'otherrollsession':
                $roll2 = $gen->create_module('rollcall', ['course' => $this->course->id]);
                $sid = $this->session((int)$roll2->id, 0);
                break;
        }

        try {
            $this->call($sid, $stid, $userids);
            $this->fail('The call must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertInstanceOf($exception, $e);
            if ($errorcode !== null) {
                $this->assertSame($errorcode, $e->errorcode);
            }
        }
        $this->assertSame(0, $DB->count_records('rollcall_attendance'));
    }

    /**
     * Cases for test_rejections.
     *
     * @return array
     */
    public static function rejections_provider(): array {
        return [
            'student' => ['student', \required_capability_exception::class, null],
            'session of another group' => ['othergroupsession', \required_capability_exception::class, null],
            'user not listed' => ['usernotlisted', \moodle_exception::class, 'usernotlisted'],
            'negative status' => ['negativestatus', \moodle_exception::class, 'invalidstatus'],
            'hidden status' => ['hiddenstatus', \moodle_exception::class, 'invalidstatus'],
            'status of another roll' => ['otherrollstatus', \moodle_exception::class, 'invalidstatus'],
            'session of another roll' => ['otherrollsession', \moodle_exception::class, 'invalidsession'],
        ];
    }
}
