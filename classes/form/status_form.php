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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use mod_rollcall\persistent\status;

/**
 * Form to create or edit an attendance status.
 *
 * Custom data: `rollcallid` (int), the roll the status belongs to.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_form extends \moodleform {
    /**
     * Defines the form elements.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'statusid', 0);
        $mform->setType('statusid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->add_action_buttons();
    }

    /**
     * Validates the name with the rules of the persistent (not blank, unique in the roll).
     *
     * @param array $data
     * @param array $files
     * @return array Errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $statusid = (int)$data['statusid'];
        $status = $statusid ? new status($statusid) : new status(0, (object)['rollcallid' => $this->_customdata['rollcallid']]);
        $status->set('name', trim($data['name']));
        $result = $status->validate();
        if (is_array($result) && isset($result['name'])) {
            $errors['name'] = (string)$result['name'];
        }
        return $errors;
    }
}
