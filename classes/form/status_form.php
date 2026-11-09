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

use mod_rollcall\local\status_manager;
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

        // Empty by default: the teacher chooses the weight of every new status.
        $mform->addElement('text', 'weight', get_string('weight', 'mod_rollcall'), ['size' => 3]);
        $mform->setType('weight', PARAM_RAW_TRIMMED);
        $mform->addRule('weight', null, 'required', null, 'client');
        $mform->addHelpButton('weight', 'weight', 'mod_rollcall');

        $this->add_action_buttons();
    }

    /**
     * Validates the weight (a whole number from 0 to 100) and the name with the rules of the persistent
     * (not blank, unique in the roll).
     *
     * @param array $data
     * @param array $files
     * @return array Errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $weight = trim((string)($data['weight'] ?? ''));
        if (!preg_match('/^\d{1,3}$/', $weight) || (int)$weight > 100) {
            $errors['weight'] = get_string('weightrange', 'mod_rollcall');
        }
        $rollcallid = (int)$this->_customdata['rollcallid'];
        $statusid = (int)$data['statusid'];
        if ($statusid) {
            // The posted id is untrusted: it must belong to this roll, or its names would be checked against another roll's.
            try {
                $status = status_manager::get_for_rollcall($statusid, $rollcallid);
            } catch (\moodle_exception $e) {
                $errors['name'] = get_string('invalidstatus', 'mod_rollcall');
                return $errors;
            }
        } else {
            $status = new status(0, (object)['rollcallid' => $rollcallid]);
        }
        $status->set('name', trim($data['name']));
        $result = $status->validate();
        if (is_array($result) && isset($result['name'])) {
            $errors['name'] = (string)$result['name'];
        }
        return $errors;
    }
}
