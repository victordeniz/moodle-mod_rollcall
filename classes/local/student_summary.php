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

namespace mod_rollcall\local;

/**
 * Attendance of a student over their past sessions.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final readonly class student_summary {
    /**
     * Constructor.
     *
     * @param int $total Past sessions of the student
     * @param int $unmarked Past sessions without a status for the student
     * @param array<int, int> $counts statusid => number of past sessions with that status (only counts above 0)
     * @param float|null $percent Attendance percentage from 0 to 100, or null without past sessions
     */
    public function __construct(
        /** @var int Past sessions of the student */
        public int $total,
        /** @var int Past sessions without a status */
        public int $unmarked,
        /** @var array<int, int> statusid => count */
        public array $counts,
        /** @var float|null Percentage, null without past sessions */
        public ?float $percent,
    ) {
    }
}
