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

use mod_rollcall\local\session_manager;

/**
 * The sessions front page: the sessions the current user sees, with their actions.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessions_page implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \cm_info $cm The roll
     * @param int $groupid Group to show, 0 for all
     * @param int $highlightid Session to highlight, 0 for none
     */
    public function __construct(
        /** @var \cm_info The roll */
        protected \cm_info $cm,
        /** @var int Group to show, 0 for all */
        protected int $groupid,
        /** @var int Session to highlight, 0 for none */
        protected int $highlightid = 0,
    ) {
    }

    /**
     * Exports the data for the mod_rollcall/sessions_page template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $cm = $this->cm;
        $context = $cm->context;
        $groups = groups_get_all_groups($cm->course);
        $dateformat = get_string('strftimedatetimeshort', 'langconfig');

        $rows = [];
        foreach (session_manager::get_visible($cm, $this->groupid) as $session) {
            $id = (int)$session->get('id');
            $sessiongroup = (int)$session->get('groupid');
            if ($sessiongroup === 0) {
                $groupname = get_string('wholeactivity', 'mod_rollcall');
            } else if (isset($groups[$sessiongroup])) {
                $groupname = format_string($groups[$sessiongroup]->name, true, ['context' => $context]);
            } else {
                $groupname = get_string('deletedgroup', 'mod_rollcall');
            }
            $canmanage = session_manager::can_manage($cm, $sessiongroup);
            $rows[] = [
                'name' => format_string($session->get('name'), true, ['context' => $context]),
                'date' => userdate((int)$session->get('sessdate'), $dateformat),
                'duration' => format_time((int)$session->get('duration')),
                'group' => $groupname,
                'highlight' => $id === $this->highlightid,
                'editurl' => $canmanage ? (new \moodle_url('/mod/rollcall/editsession.php', [
                    'id' => $cm->id,
                    'sessionid' => $id,
                    'group' => $this->groupid,
                ]))->out(false) : null,
                'deleteurl' => $canmanage ? (new \moodle_url('/mod/rollcall/view.php', [
                    'id' => $cm->id,
                    'action' => 'delete',
                    'sessionid' => $id,
                    'group' => $sessiongroup,
                    'sesskey' => sesskey(),
                ]))->out(false) : null,
            ];
        }

        return [
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'addurl' => session_manager::can_create($cm) ? (new \moodle_url('/mod/rollcall/editsession.php', [
                'id' => $cm->id,
                'group' => $this->groupid,
            ]))->out(false) : null,
        ];
    }
}
