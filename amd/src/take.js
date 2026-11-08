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
 * Saves the attendance of a session, one student at a time or in bulk.
 *
 * @module     mod_rollcall/take
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';
import {add as addToast} from 'core/toast';
import {getString} from 'core/str';

const SELECTORS = {
    ROOT: '[data-region="mod_rollcall-take"]',
    TABLE: '[data-region="mod_rollcall-take-table"]',
    ROW: 'tr[data-userid]',
    SETSTATUS: '[data-action="setstatus"]',
    SELECT: '[data-action="select"]',
    SELECTALL: '[data-action="selectall"]',
    BULKAPPLY: '[data-action="bulkapply"]',
    BULKSTATUS: '[data-region="bulkstatus"]',
};

/**
 * Calls the web service that sets the attendance status of the students.
 *
 * @param {Number} cmid
 * @param {Number} sessionid
 * @param {Number} statusid 0 for no status
 * @param {Number[]} userids
 * @returns {Promise<Array<{userid: Number, statusid: Number}>>}
 */
const setAttendance = (cmid, sessionid, statusid, userids) => fetchMany([{
    methodname: 'mod_rollcall_set_attendance',
    args: {cmid, sessionid, statusid, userids},
}])[0];

/**
 * Saves a status for some rows and updates them.
 *
 * @param {HTMLElement} root
 * @param {Number} cmid
 * @param {Number} sessionid
 * @param {Number} statusid
 * @param {HTMLElement[]} rows
 * @returns {Promise<Boolean>} Whether it was saved
 */
const save = async(root, cmid, sessionid, statusid, rows) => {
    const controls = rows.map((row) => row.querySelector(SELECTORS.SETSTATUS));
    controls.forEach((control) => {
        control.disabled = true;
    });
    try {
        const userids = rows.map((row) => parseInt(row.dataset.userid, 10));
        const result = await setAttendance(cmid, sessionid, statusid, userids);
        result.forEach(({userid, statusid: newstatusid}) => {
            const row = root.querySelector(`${SELECTORS.ROW}[data-userid="${userid}"]`);
            if (row) {
                row.dataset.statusid = String(newstatusid);
                row.querySelector(SELECTORS.SETSTATUS).value = String(newstatusid);
            }
        });
        addToast(await getString('saved', 'mod_rollcall'));
        return true;
    } catch (error) {
        // Put the selects back to the stored status.
        rows.forEach((row) => {
            row.querySelector(SELECTORS.SETSTATUS).value = row.dataset.statusid;
        });
        Notification.exception(error);
        return false;
    } finally {
        controls.forEach((control) => {
            control.disabled = false;
        });
    }
};

/**
 * Sets up the attendance controls of a session.
 *
 * @param {Number} cmid
 * @param {Number} sessionid
 */
export const init = (cmid, sessionid) => {
    const root = document.querySelector(`${SELECTORS.ROOT}[data-cmid="${cmid}"][data-sessionid="${sessionid}"]`);
    // Without students there is no table, so there is nothing to set up.
    if (!root || !root.querySelector(SELECTORS.TABLE)) {
        return;
    }

    root.addEventListener('change', (e) => {
        if (e.target.matches(SELECTORS.SETSTATUS)) {
            const row = e.target.closest(SELECTORS.ROW);
            save(root, cmid, sessionid, parseInt(e.target.value, 10), [row]);
        } else if (e.target.matches(SELECTORS.SELECTALL)) {
            root.querySelectorAll(SELECTORS.SELECT).forEach((checkbox) => {
                checkbox.checked = e.target.checked;
            });
        }
    });

    root.addEventListener('click', async(e) => {
        if (!e.target.closest(SELECTORS.BULKAPPLY)) {
            return;
        }
        const rows = [...root.querySelectorAll(`${SELECTORS.SELECT}:checked`)].map((c) => c.closest(SELECTORS.ROW));
        if (!rows.length) {
            addToast(await getString('noselection', 'mod_rollcall'), {type: 'warning'});
            return;
        }
        const statusid = parseInt(root.querySelector(SELECTORS.BULKSTATUS).value, 10);
        await save(root, cmid, sessionid, statusid, rows);
        root.querySelectorAll(`${SELECTORS.SELECT}, ${SELECTORS.SELECTALL}`).forEach((checkbox) => {
            checkbox.checked = false;
        });
    });

    // The controls are rendered disabled until they can be saved.
    root.querySelectorAll(
        `${SELECTORS.SETSTATUS}, ${SELECTORS.SELECT}, ${SELECTORS.SELECTALL}, ${SELECTORS.BULKSTATUS}, ${SELECTORS.BULKAPPLY}`
    ).forEach((control) => {
        control.disabled = false;
    });
};
