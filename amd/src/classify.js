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
 * Classifies students from the roll page, one by one or in bulk.
 *
 * @module     mod_rollcall/classify
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';
import {add as addToast} from 'core/toast';
import {getString} from 'core/str';

const SELECTORS = {
    ROOT: '[data-region="mod_rollcall-root"]',
    ROW: 'tr[data-userid]',
    SETCATEGORY: '[data-action="setcategory"]',
    SELECT: '[data-action="select"]',
    SELECTALL: '[data-action="selectall"]',
    BULKAPPLY: '[data-action="bulkapply"]',
    BULKCATEGORY: '[data-region="bulkcategory"]',
    BADGE: '[data-region="badge"]',
    COUNT: '[data-region="count"]',
};

const ROW_CLASS_PREFIX = 'mod_rollcall-row-';
const BADGE_CLASS_PREFIX = 'mod_rollcall-badge-';

/**
 * Calls the web service that sets the category of the students.
 *
 * @param {Number} cmid
 * @param {Number} categoryid 0 for regular
 * @param {Number[]} userids
 * @returns {Promise<Array<{userid: Number, categoryid: Number}>>}
 */
const setCategory = (cmid, categoryid, userids) => fetchMany([{
    methodname: 'mod_rollcall_set_category',
    args: {cmid, categoryid, userids},
}])[0];

/**
 * Returns the name and colour of a category, read from the bulk select options.
 *
 * @param {HTMLElement} root
 * @param {Number} categoryid
 * @returns {{name: String, color: String}}
 */
const getCategoryInfo = (root, categoryid) => {
    const option = root.querySelector(`${SELECTORS.BULKCATEGORY} option[value="${categoryid}"]`);
    return {
        name: option ? option.textContent : '',
        color: option ? option.dataset.color : '',
    };
};

/**
 * Replaces the classes starting with a prefix.
 *
 * @param {HTMLElement} element
 * @param {String} prefix
 * @param {String} color New colour, or empty for none
 */
const setColorClass = (element, prefix, color) => {
    [...element.classList].filter((c) => c.startsWith(prefix)).forEach((c) => element.classList.remove(c));
    if (color) {
        element.classList.add(prefix + color);
    }
};

/**
 * Adds a delta to the legend count of a category.
 *
 * @param {HTMLElement} root
 * @param {Number} categoryid
 * @param {Number} delta
 */
const adjustLegend = (root, categoryid, delta) => {
    const count = root.querySelector(`[data-region="legend"] [data-categoryid="${categoryid}"] ${SELECTORS.COUNT}`);
    if (count) {
        count.textContent = String(parseInt(count.textContent, 10) + delta);
    }
};

/**
 * Shows a new category on a row: colour, badge, select and legend.
 *
 * @param {HTMLElement} root
 * @param {HTMLElement} row
 * @param {Number} categoryid
 */
const showCategory = (root, row, categoryid) => {
    const oldcategoryid = parseInt(row.dataset.categoryid, 10);
    const {name, color} = getCategoryInfo(root, categoryid);

    setColorClass(row, ROW_CLASS_PREFIX, color);
    row.dataset.categoryid = String(categoryid);
    row.querySelector(SELECTORS.SETCATEGORY).value = String(categoryid);

    const badge = row.querySelector(SELECTORS.BADGE);
    badge.textContent = name;
    setColorClass(badge, BADGE_CLASS_PREFIX, color);
    badge.classList.toggle('d-none', categoryid === 0);

    if (oldcategoryid !== categoryid) {
        adjustLegend(root, oldcategoryid, -1);
        adjustLegend(root, categoryid, 1);
    }
};

/**
 * Saves a category for some rows and updates them.
 *
 * @param {HTMLElement} root
 * @param {Number} cmid
 * @param {Number} categoryid
 * @param {HTMLElement[]} rows
 * @returns {Promise}
 */
const classify = async(root, cmid, categoryid, rows) => {
    const controls = rows.map((row) => row.querySelector(SELECTORS.SETCATEGORY));
    controls.forEach((control) => {
        control.disabled = true;
    });
    try {
        const userids = rows.map((row) => parseInt(row.dataset.userid, 10));
        const result = await setCategory(cmid, categoryid, userids);
        result.forEach(({userid, categoryid: newcategoryid}) => {
            const row = root.querySelector(`${SELECTORS.ROW}[data-userid="${userid}"]`);
            if (row) {
                showCategory(root, row, newcategoryid);
            }
        });
        addToast(await getString('saved', 'mod_rollcall'));
    } catch (error) {
        // Put the selects back to the stored category.
        rows.forEach((row) => {
            row.querySelector(SELECTORS.SETCATEGORY).value = row.dataset.categoryid;
        });
        Notification.exception(error);
    } finally {
        controls.forEach((control) => {
            control.disabled = false;
        });
    }
};

/**
 * Sets up the classification controls of a roll.
 *
 * @param {Number} cmid
 */
export const init = (cmid) => {
    const root = document.querySelector(`${SELECTORS.ROOT}[data-cmid="${cmid}"]`);
    if (!root) {
        return;
    }

    root.addEventListener('change', (e) => {
        if (e.target.matches(SELECTORS.SETCATEGORY)) {
            const row = e.target.closest(SELECTORS.ROW);
            classify(root, cmid, parseInt(e.target.value, 10), [row]);
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
        const categoryid = parseInt(root.querySelector(SELECTORS.BULKCATEGORY).value, 10);
        await classify(root, cmid, categoryid, rows);
        root.querySelectorAll(`${SELECTORS.SELECT}, ${SELECTORS.SELECTALL}`).forEach((checkbox) => {
            checkbox.checked = false;
        });
    });
};
