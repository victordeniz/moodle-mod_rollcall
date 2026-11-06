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

/**
 * Form to create or edit a session.
 *
 * Custom data: `groupoptions` (array of groupid => name, empty when the activity has no groups) and
 * `requiregroupchoice` (bool, whether the user must pick a group because there is no sensible default).
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_form extends \moodleform {
    /**
     * Defines the form elements.
     */
    protected function definition() {
        $mform = $this->_form;
        $groupoptions = $this->_customdata['groupoptions'] ?? [];
        $requiregroupchoice = !empty($this->_customdata['requiregroupchoice']);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sessionid', 0);
        $mform->setType('sessionid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('date_time_selector', 'sessdate', get_string('sessiondate', 'mod_rollcall'));

        // Raw text, so that "1.5" or "abc" are not silently cleaned into an integer before validation.
        $mform->addElement('text', 'durationminutes', get_string('durationminutes', 'mod_rollcall'), ['size' => 5]);
        $mform->setType('durationminutes', PARAM_RAW_TRIMMED);
        $mform->addRule('durationminutes', null, 'required', null, 'client');

        if (!empty($groupoptions)) {
            if ($requiregroupchoice) {
                $groupoptions = ['' => get_string('choosegroup', 'mod_rollcall')] + $groupoptions;
            }
            $mform->addElement('select', 'groupid', get_string('group'), $groupoptions);
            if ($requiregroupchoice) {
                $mform->addRule('groupid', null, 'required', null, 'client');
            }
        }

        $this->add_action_buttons();
    }

    /**
     * Validates the duration (whole minutes, 1 to 1440) and the group choice.
     *
     * @param array $data
     * @param array $files
     * @return array Errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim(clean_param($data['name'] ?? '', PARAM_TEXT)) === '') {
            $errors['name'] = get_string('required');
        }
        $minutes = trim((string)($data['durationminutes'] ?? ''));
        if (!preg_match('/^[0-9]+$/', $minutes) || (int)$minutes < 1 || (int)$minutes > 1440) {
            $errors['durationminutes'] = get_string('invalidduration', 'mod_rollcall');
        }
        if (
            !empty($this->_customdata['requiregroupchoice'])
            && !empty($this->_customdata['groupoptions'])
            && (!isset($data['groupid']) || $data['groupid'] === '')
        ) {
            $errors['groupid'] = get_string('required');
        }
        return $errors;
    }
}
