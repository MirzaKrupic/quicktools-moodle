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
 * @copyright  2026 Bioptimizers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str', 'local_quicktools/editmode_scroll'],
    function(Ajax, Notification, Str, Scroll) {

    var PICK = 'input.local-quicktools-pick';

    var parseMarks = function(text) {
        var value = parseFloat(String(text).replace(',', '.'));
        return (isFinite(value) && value >= 0) ? value : null;
    };

    var el = function(tag, props) {
        var node = document.createElement(tag);
        Object.keys(props || {}).forEach(function(key) {
            node[key] = props[key];
        });
        return node;
    };

    var selectedSlotIds = function() {
        var ids = [];
        document.querySelectorAll(PICK + ':checked').forEach(function(box) {
            ids.push(parseInt(box.value, 10));
        });
        return ids;
    };

    return {
        init: function(cmid) {
            var toolbar = document.querySelector('.mod_quiz-edit-action-buttons');
            var container = document.querySelector('.mod_quiz-edit-top-controls');
            var slots = document.querySelectorAll('li.slot');
            if (!toolbar || !container || !slots.length) {
                return;
            }

            Str.get_strings([
                {key: 'bulkmarks', component: 'local_quicktools'},
                {key: 'marksfield', component: 'local_quicktools'},
                {key: 'setmarksselected', component: 'local_quicktools'},
                {key: 'marksinvalid', component: 'local_quicktools'},
                {key: 'marksupdated', component: 'local_quicktools'},
                {key: 'pickquestion', component: 'local_quicktools'},
                {key: 'selectall', component: 'moodle'},
                {key: 'cancel', component: 'moodle'}
            ]).then(function(str) {
                var toggle = el('button', {type: 'button', className: 'btn btn-secondary', textContent: str[0]});
                toolbar.appendChild(toggle);

                var panel = el('div', {className: 'btn-toolbar m-1 align-items-center d-none local-quicktools-panel'});
                var all = el('input', {type: 'checkbox', id: 'local-quicktools-all', className: 'mr-1'});
                var allLabel = el('label', {htmlFor: 'local-quicktools-all', className: 'mr-3 mb-0', textContent: str[6]});
                var input = el('input', {type: 'text', className: 'form-control mr-2', placeholder: '0.00'});
                input.style.width = '6rem';
                input.inputMode = 'decimal';
                input.setAttribute('aria-label', str[1]);
                var apply = el('button', {type: 'button', className: 'btn btn-primary mr-2', textContent: str[2], disabled: true});
                var cancel = el('button', {type: 'button', className: 'btn btn-secondary', textContent: str[7]});
                [all, allLabel, input, apply, cancel].forEach(function(node) {
                    panel.appendChild(node);
                });
                container.appendChild(panel);

                var picks = [];
                slots.forEach(function(slot) {
                    var match = /^slot-(\d+)$/.exec(slot.id);
                    var target = slot.querySelector('.activityinstance');
                    if (!match || !target) {
                        return;
                    }
                    var number = slot.querySelector('.slotnumber');
                    var box = el('input', {type: 'checkbox', value: match[1], className: 'local-quicktools-pick d-none mr-2'});
                    box.setAttribute('aria-label', str[5] + (number ? ' ' + number.textContent.trim() : ''));
                    target.insertBefore(box, target.firstChild);
                    picks.push(box);
                });

                var refresh = function() {
                    var ids = selectedSlotIds();
                    apply.disabled = !ids.length || parseMarks(input.value) === null;
                    all.checked = ids.length === picks.length;
                };
                var setOpen = function(open) {
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
                    var marks = parseMarks(input.value);
                    var slotids = selectedSlotIds();
                    if (marks === null || !slotids.length) {
                        Notification.alert('', str[3]);
                        return;
                    }
                    apply.disabled = true;
                    Ajax.call([{
                        methodname: 'local_quicktools_set_slot_marks',
                        args: {cmid: cmid, slotids: slotids, maxmark: marks}
                    }])[0].then(function(result) {
                        Notification.addNotification({
                            message: str[4].replace('{$a}', result.updated),
                            type: 'success'
                        });
                        Scroll.saveState();
                        window.setTimeout(function() {
                            window.location.reload();
                        }, 600);
                        return;
                    }).catch(function(error) {
                        apply.disabled = false;
                        Notification.exception(error);
                    });
                });
                return;
            }).catch(Notification.exception);
        }
    };
});
