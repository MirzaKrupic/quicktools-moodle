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
 * Adds a "Set marks in bulk" tool to the quiz Questions page.
 *
 * It has its own toggle and checkboxes (instead of Moodle's "Select multiple items") because
 * Moodle disables that button once a quiz has attempts, yet changing marks is still allowed then.
 *
 * @module     local_quicktools/quiz_bulkmarks
 * @copyright  2026 Mirza Krupic
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {call as fetchMany} from 'core/ajax';
import {addNotification, alert as displayAlert, exception as displayException} from 'core/notification';
import {get_strings as getStrings} from 'core/str';
import {saveState} from 'local_quicktools/editmode_scroll';


const PICK = 'input.local-quicktools-pick';

const parseMarks = function(text) {
    const value = parseFloat(String(text).replace(',', '.'));
    return (isFinite(value) && value >= 0) ? value : null;
};

const el = function(tag, props) {
    const node = document.createElement(tag);
    Object.keys(props || {}).forEach(function(key) {
        node[key] = props[key];
    });
    return node;
};

const selectedSlotIds = function() {
    const ids = [];
    document.querySelectorAll(PICK + ':checked').forEach(function(box) {
        ids.push(parseInt(box.value, 10));
    });
    return ids;
};

/**
 * Add the bulk marks tool to the quiz Questions page.
 *
 * @param {Number} cmid quiz course module id
 */
export const init = cmid => {
    const toolbar = document.querySelector('.mod_quiz-edit-action-buttons');
    const container = document.querySelector('.mod_quiz-edit-top-controls');
    const slots = document.querySelectorAll('li.slot');
    if (!toolbar || !container || !slots.length) {
        return;
    }

    const build = str => {
        const toggle = el('button', {type: 'button', className: 'btn btn-secondary', textContent: str[0]});
        toolbar.appendChild(toggle);

        const panel = el('div', {className: 'btn-toolbar m-1 align-items-center d-none local-quicktools-panel'});
        const all = el('input', {type: 'checkbox', id: 'local-quicktools-all', className: 'mr-1'});
        const allLabel = el('label', {htmlFor: 'local-quicktools-all', className: 'mr-3 mb-0', textContent: str[6]});
        const input = el('input', {type: 'text', className: 'form-control mr-2', placeholder: '0.00'});
        input.style.width = '6rem';
        input.inputMode = 'decimal';
        input.setAttribute('aria-label', str[1]);
        const apply = el('button', {type: 'button', className: 'btn btn-primary mr-2', textContent: str[2], disabled: true});
        const cancel = el('button', {type: 'button', className: 'btn btn-secondary', textContent: str[7]});
        [all, allLabel, input, apply, cancel].forEach(function(node) {
            panel.appendChild(node);
        });
        container.appendChild(panel);

        const picks = [];
        slots.forEach(function(slot) {
            const match = /^slot-(\d+)$/.exec(slot.id);
            const target = slot.querySelector('.activityinstance');
            if (!match || !target) {
                return;
            }
            const number = slot.querySelector('.slotnumber');
            const box = el('input', {type: 'checkbox', value: match[1], className: 'local-quicktools-pick d-none mr-2'});
            box.setAttribute('aria-label', str[5] + (number ? ' ' + number.textContent.trim() : ''));
            target.insertBefore(box, target.firstChild);
            picks.push(box);
        });

        const refresh = function() {
            const ids = selectedSlotIds();
            apply.disabled = !ids.length || parseMarks(input.value) === null;
            all.checked = ids.length === picks.length;
        };
        const setOpen = function(open) {
            panel.classList.toggle('d-none', !open);
            picks.forEach(function(box) {
                box.classList.toggle('d-none', !open);
                if (!open) {
                    box.checked = false;
                }
            });
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            refresh();
            if (open) {
                input.focus();
            }
        };

        toggle.addEventListener('click', function() {
            setOpen(panel.classList.contains('d-none'));
        });
        cancel.addEventListener('click', function() {
            setOpen(false);
        });
        all.addEventListener('change', function() {
            picks.forEach(function(box) {
                box.checked = all.checked;
            });
            refresh();
        });
        input.addEventListener('input', refresh);
        picks.forEach(function(box) {
            box.addEventListener('change', refresh);
        });
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !apply.disabled) {
                e.preventDefault();
                apply.click();
            }
        });

        apply.addEventListener('click', function() {
            const marks = parseMarks(input.value);
            const slotids = selectedSlotIds();
            if (marks === null || !slotids.length) {
                displayAlert('', str[3]);
                return;
            }
            apply.disabled = true;
            fetchMany([{
                methodname: 'local_quicktools_set_slot_marks',
                args: {cmid: cmid, slotids: slotids, maxmark: marks}
            }])[0].then(function(result) {
                addNotification({
                    message: str[4].replace('{$a}', result.updated),
                    type: 'success'
                });
                saveState();
                window.setTimeout(function() {
                    window.location.reload();
                }, 600);
                return;
            }).catch(function(error) {
                apply.disabled = false;
                displayException(error);
            });
        });
    };

    getStrings([
        {key: 'bulkmarks', component: 'local_quicktools'},
        {key: 'marksfield', component: 'local_quicktools'},
        {key: 'setmarksselected', component: 'local_quicktools'},
        {key: 'marksinvalid', component: 'local_quicktools'},
        {key: 'marksupdated', component: 'local_quicktools'},
        {key: 'pickquestion', component: 'local_quicktools'},
        {key: 'selectall', component: 'moodle'},
        {key: 'cancel', component: 'moodle'}
    ]).then(str => {
        build(str);
        return str;
    }).catch(displayException);
};
