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

/**
 * English strings for mod_rollcall.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcategory'] = 'Add category';
$string['addsession'] = 'New session';
$string['addstatus'] = 'Add status';
$string['apply'] = 'Apply';
$string['attendancepercent'] = 'Attendance';
$string['backtosessions'] = 'Back to sessions';
$string['category'] = 'Category';
$string['categorydeleted'] = 'Category deleted.';
$string['categoryfor'] = 'Category for {$a}';
$string['categoryinuse'] = 'This category is assigned to students; hide it instead of deleting it.';
$string['choosegroup'] = 'Choose a group…';
$string['color'] = 'Colour';
$string['color_amber'] = 'Amber';
$string['color_blue'] = 'Blue';
$string['color_green'] = 'Green';
$string['color_pink'] = 'Pink';
$string['color_purple'] = 'Purple';
$string['color_red'] = 'Red';
$string['color_slate'] = 'Slate';
$string['color_teal'] = 'Teal';
$string['confirmdeletecategory'] = 'Delete the category "{$a}"?';
$string['confirmdeletesession'] = 'Delete the session "{$a}"?';
$string['confirmdeletesessionwithattendance'] = 'Delete the session "{$a->name}"? It has attendance recorded for {$a->count} students, which will be deleted.';
$string['confirmdeletestatus'] = 'Delete the status "{$a}"?';
$string['confirmrestorestatuses'] = 'Add the default statuses that are missing? Existing statuses are not changed.';
$string['defaultcat_erasmus'] = 'Erasmus';
$string['defaultcat_repeater'] = 'Repeater';
$string['defaultsessionname'] = 'Session {$a}';
$string['defaultsrestored'] = 'Default statuses added: {$a}.';
$string['defaultstatus_absent'] = 'Absent';
$string['defaultstatus_late'] = 'Late';
$string['defaultstatus_present'] = 'Present';
$string['deletedgroup'] = 'Deleted group';
$string['duration'] = 'Duration';
$string['durationminutes'] = 'Duration (minutes)';
$string['editcategory'] = 'Edit category';
$string['editsession'] = 'Edit session';
$string['editstatus'] = 'Edit status';
$string['eventattendanceupdated'] = 'Attendance updated';
$string['eventcategorycreated'] = 'Student category created';
$string['eventcategorydeleted'] = 'Student category deleted';
$string['eventcategoryupdated'] = 'Student category updated';
$string['eventcoursemoduleviewed'] = 'Roll viewed';
$string['eventreportviewed'] = 'Attendance report viewed';
$string['eventsessioncreated'] = 'Session created';
$string['eventsessiondeleted'] = 'Session deleted';
$string['eventsessionupdated'] = 'Session updated';
$string['eventstatuscreated'] = 'Attendance status created';
$string['eventstatusdeleted'] = 'Attendance status deleted';
$string['eventstatusupdated'] = 'Attendance status updated';
$string['eventusercategoryupdated'] = 'Student category changed';
$string['filter'] = 'Show';
$string['filterall'] = 'All students';
$string['gradeslastsynced'] = 'Grades last sent to the gradebook: {$a}';
$string['gradesneversynced'] = 'The grades have not been sent to the gradebook yet.';
$string['gradesnotupdated'] = 'The grades could not be updated: the grade item of this activity, or the grade of some student, is locked in the gradebook. Unlock it there and try again.';
$string['gradesupdated'] = 'Grades updated: {$a} students';
$string['invalidcategory'] = 'Invalid category.';
$string['invalidcolor'] = 'The colour is not in the palette.';
$string['invalidduration'] = 'The duration must be between 1 and 1440 minutes.';
$string['invalidsession'] = 'Invalid session.';
$string['invalidstatus'] = 'Invalid attendance status.';
$string['legend'] = 'Students per category';
$string['managecategories'] = 'Student categories';
$string['modulename'] = 'Roll call';
$string['modulename_help'] = 'The roll call activity lists the students of the course and lets teachers classify them into categories (for example repeater or Erasmus), each shown with its own colour. It also records sessions (individual classes) with their date, duration and group.';
$string['modulename_link'] = '%%WWWROOT%%/mod/rollcall/manual/index.html';
$string['modulenameplural'] = 'Roll calls';
$string['nocategories'] = 'There are no categories yet.';
$string['nogradeinactivity'] = 'This activity has no grade.';
$string['nopastsessions'] = 'There are no past sessions yet.';
$string['noselection'] = 'Select at least one student first.';
$string['nosessions'] = 'There are no sessions yet.';
$string['nostatus'] = 'No status';
$string['nostatuses'] = 'There are no statuses yet.';
$string['nostatusesvisible'] = 'This roll has no visible attendance statuses.';
$string['nostudents'] = 'There are no students to show.';
$string['notingroup'] = 'You are not a member of any group, so there are no students to show.';
$string['notingroupsessions'] = 'You are not a member of any group, so there are no sessions to show.';
$string['order'] = 'Order';
$string['paginate'] = 'Paginate ({$a} per page)';
$string['percentvalue'] = '{$a} %';
$string['perpage'] = 'Students per page';
$string['perpage_desc'] = 'Page size of the attendance report, and of the take attendance page when its list is paginated. Between 1 and 500.';
$string['pluginadministration'] = 'Roll call administration';
$string['pluginname'] = 'Roll call';
$string['privacy:metadata:core_grades'] = 'The attendance grade of the student is stored in the gradebook.';
$string['privacy:metadata:rollcall_attendance'] = 'Attendance records of students in the sessions of a roll.';
$string['privacy:metadata:rollcall_attendance:sessionid'] = 'The session the attendance belongs to.';
$string['privacy:metadata:rollcall_attendance:statusid'] = 'The status assigned to the student.';
$string['privacy:metadata:rollcall_attendance:timecreated'] = 'When the attendance was first recorded.';
$string['privacy:metadata:rollcall_attendance:timemodified'] = 'When the attendance was last modified.';
$string['privacy:metadata:rollcall_attendance:userid'] = 'The student whose attendance was recorded.';
$string['privacy:metadata:rollcall_category'] = 'Site-wide catalogue of student categories.';
$string['privacy:metadata:rollcall_category:timemodified'] = 'When the category was last modified.';
$string['privacy:metadata:rollcall_category:usermodified'] = 'The user who last modified the category.';
$string['privacy:metadata:rollcall_usercat'] = 'The category of a student in a course.';
$string['privacy:metadata:rollcall_usercat:categoryid'] = 'The category assigned to the student.';
$string['privacy:metadata:rollcall_usercat:timemodified'] = 'When the classification was last modified.';
$string['privacy:metadata:rollcall_usercat:userid'] = 'The classified student.';
$string['privacy:metadata:rollcall_usercat:usermodified'] = 'The user who classified the student.';
$string['privacy:path'] = 'Student classification';
$string['privacy:pathattendance'] = 'Attendance';
$string['regular'] = 'Regular';
$string['report'] = 'Attendance report';
$string['reporthelp'] = 'Percentage over each student\'s past sessions. Unmarked sessions count as 0.';
$string['resetattendance'] = 'Delete recorded attendance (keep the sessions)';
$string['resetclassifications'] = 'Delete student classifications';
$string['resetsessions'] = 'Delete all sessions';
$string['restoredefaultstatuses'] = 'Restore default statuses';
$string['rollcall:addinstance'] = 'Add a new roll call';
$string['rollcall:belisted'] = 'Be listed as a student in the roll';
$string['rollcall:classify'] = 'Classify students';
$string['rollcall:managesessions'] = 'Manage the sessions';
$string['rollcall:managestatuses'] = 'Manage the attendance statuses';
$string['rollcall:takeattendance'] = 'Take attendance';
$string['rollcall:updategrades'] = 'Update the grades in the gradebook';
$string['rollcall:view'] = 'View the roll';
$string['rollcall:viewreport'] = 'View the attendance report';
$string['saved'] = 'Saved';
$string['scalesnotsupported'] = 'Scales are not supported. Choose "None" or "Point".';
$string['selectall'] = 'Select all students';
$string['selectuser'] = 'Select {$a}';
$string['sessioncreated'] = 'Session created.';
$string['sessiondate'] = 'Date and time';
$string['sessiondeleted'] = 'Session deleted.';
$string['sessions'] = 'Sessions';
$string['sessionupdated'] = 'Session updated.';
$string['showall'] = 'Show all ({$a})';
$string['status'] = 'Status';
$string['statusdeleted'] = 'Status deleted.';
$string['statuses'] = 'Statuses';
$string['statusfor'] = 'Status of {$a}';
$string['statushidden'] = '{$a} (hidden)';
$string['statusinuse'] = 'This status has been used to record attendance; hide it instead of deleting it.';
$string['statusnameexists'] = 'A status with this name already exists in this roll.';
$string['statusweight'] = '{$a->name} ({$a->weight})';
$string['student'] = 'Student';
$string['students'] = 'Students';
$string['studentsclassified'] = 'Students classified';
$string['takeattendance'] = 'Take attendance';
$string['unmarked'] = 'Unmarked';
$string['unmarkedwarning'] = 'Some past sessions are unmarked and count as 0';
$string['updategrades'] = 'Update grades';
$string['usernotlisted'] = 'The user is not a student listed in this roll.';
$string['visible'] = 'Visible';
$string['weight'] = 'Weight';
$string['weight_help'] = 'Value of a session with this status, from 0 to 100, where 100 is a full session. Used to calculate the attendance percentage.';
$string['weightrange'] = 'Enter a whole number between 0 and 100.';
$string['wholeactivity'] = 'Whole activity';
$string['withselected'] = 'With selected students:';
