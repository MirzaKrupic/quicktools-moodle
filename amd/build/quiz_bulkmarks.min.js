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
 * Adds "Set marks for selected" to the quiz editor's "Select multiple items" toolbar.
 *
 * @module     local_quicktools/quiz_bulkmarks
 * @copyright  2026 Bioptimizers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str', 'local_quicktools/editmode_scroll'],
    function(Ajax, Notification, Str, Scroll) {

    var CHECKBOXES = '.select-multiple-checkbox';

    var parseMarks = function(text) {
        var value = parseFloat(String(text).replace(',', '.'));
        return (isFinite(value) && value >= 0) ? value : null;
    };

    var selectedSlotIds = function() {
        var ids = [];
        document.querySelectorAll('input' + CHECKBOXES + ':checked').forEach(function(box) {
            var slot = box.closest('li.slot');
            var match = slot && /^slot-(\d+)$/.exec(slot.id);
            if (match) {
                ids.push(parseInt(match[1], 10));
            }
        });
        return ids;
    };

    var build = function(strings) {
        var group = document.createElement('div');
        group.className = 'btn-group selectmultiplecommand actions m-1 local-quicktools-marks';
        group.setAttribute('role', 'group');

        var input = document.createElement('input');
        input.type = 'text';
        input.inputMode = 'decimal';
        input.className = 'form-control';
        input.style.width = '6rem';
        input.placeholder = '0.00';
        input.setAttribute('aria-label', strings[0]);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary';
        button.textContent = strings[1];
        button.disabled = true;

        group.appendChild(input);
        group.appendChild(button);
        return {group: group, input: input, button: button};
    };

    return {
        init: function(cmid) {
            var anchor = document.querySelector('#selectmultipledeletecommand');
            var toolbar = anchor && anchor.closest('.selectmultiplecommand');
            if (!toolbar) {
                return;
            }

            Str.get_strings([
                {key: 'marksfield', component: 'local_quicktools'},
                {key: 'setmarksselected', component: 'local_quicktools'},
                {key: 'marksinvalid', component: 'local_quicktools'},
                {key: 'marksupdated', component: 'local_quicktools'}
            ]).then(function(strings) {
                var ui = build(strings);
                toolbar.parentNode.insertBefore(ui.group, toolbar.nextSibling);

                var refresh = function() {
                    ui.button.disabled = !selectedSlotIds().length || parseMarks(ui.input.value) === null;
                };
                ui.input.addEventListener('input', refresh);
                document.addEventListener('change', function(e) {
                    if (e.target && e.target.matches && e.target.matches(CHECKBOXES + ', #questionselectall')) {
                        refresh();
                    }
                });
                // Select all / deselect all toggles the boxes without a change event on each.
                document.addEventListener('click', function(e) {
                    if (e.target && e.target.closest && e.target.closest('.selectmultiplecommandbuttons')) {
                        window.setTimeout(refresh, 0);
                    }
                });

                ui.button.addEventListener('click', function() {
                    var marks = parseMarks(ui.input.value);
                    var slotids = selectedSlotIds();
                    if (marks === null || !slotids.length) {
                        Notification.alert('', strings[2]);
                        return;
                    }
                    ui.button.disabled = true;
                    Ajax.call([{
                        methodname: 'local_quicktools_set_slot_marks',
                        args: {cmid: cmid, slotids: slotids, maxmark: marks}
                    }])[0].then(function(result) {
                        Notification.addNotification({
                            message: strings[3].replace('{$a}', result.updated),
                            type: 'success'
                        });
                        Scroll.saveState();
                        window.setTimeout(function() {
                            window.location.reload();
                        }, 600);
                        return;
                    }).catch(function(error) {
                        ui.button.disabled = false;
                        Notification.exception(error);
                    });
                });
                return;
            }).catch(Notification.exception);
        }
    };
});
