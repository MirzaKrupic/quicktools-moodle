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

/**
 * Preset bar for the quiz settings "Review options" grid: apply a preset in one click,
 * save the current grid as your own preset, and choose a default for new quizzes.
 *
 * @module     local_quicktools/review_presets
 * @copyright  2026 Bioptimizers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {

    var WHEN = ['during', 'immediately', 'open', 'closed'];
    var OPTIONS = ['attempt', 'correctness', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer',
        'overallfeedback'];

    var box = function(option, when) {
        return document.getElementById('id_' + option + when);
    };

    var readGrid = function() {
        var grid = {};
        WHEN.forEach(function(when) {
            grid[when] = {};
            OPTIONS.forEach(function(option) {
                var el = box(option, when);
                grid[when][option] = el && el.checked ? 1 : 0;
            });
        });
        return grid;
    };

    var applyGrid = function(grid) {
        WHEN.forEach(function(when) {
            // "The attempt" first, so Moodle's own enable/disable rules settle before the rest.
            OPTIONS.forEach(function(option) {
                var el = box(option, when);
                var wanted = !!(grid[when] && grid[when][option]);
                if (el && el.checked !== wanted) {
                    el.checked = wanted;
                    el.dispatchEvent(new Event('change', {bubbles: true}));
                }
            });
        });
    };

    var el = function(tag, props, children) {
        var node = document.createElement(tag);
        Object.keys(props || {}).forEach(function(key) {
            node[key] = props[key];
        });
        (children || []).forEach(function(child) {
            node.appendChild(child);
        });
        return node;
    };

    return {
        init: function(data) {
            var container = document.getElementById('id_reviewoptionshdrcontainer');
            if (!container || !document.getElementById('id_attemptduring')) {
                return;
            }
            var state = {presets: data.presets, defaultRef: data.default};

            Str.get_strings([
                'presetapply', 'presetchoose', 'presetsave', 'presetname', 'presetsavebtn', 'presetcancel',
                'presetdelete', 'presetdeleteq', 'presetsetdefault', 'presetisdefault', 'presetnodefault',
                'presetsaved', 'presetyours', 'presetbuiltin', 'presetapplied'
            ].map(function(key) {
                return {key: key, component: 'local_quicktools'};
            })).then(function(str) {
                var S = {apply: str[0], choose: str[1], save: str[2], name: str[3], savebtn: str[4], cancel: str[5],
                    del: str[6], delq: str[7], setdefault: str[8], isdefault: str[9], nodefault: str[10],
                    saved: str[11], yours: str[12], builtin: str[13], applied: str[14]};

                var bar = el('div', {className: 'd-flex flex-wrap align-items-center mb-3 local-quicktools-presets'});
                var label = el('label', {htmlFor: 'local-quicktools-preset', className: 'mr-2 mb-0', textContent: S.apply});
                var select = el('select', {id: 'local-quicktools-preset', className: 'custom-select mr-2'});
                select.style.width = 'auto';
                select.style.maxWidth = '30rem';
                var saveBtn = el('button', {type: 'button', className: 'btn btn-secondary mr-2', textContent: S.save});
                var defBtn = el('button', {type: 'button', className: 'btn btn-secondary mr-2', disabled: true});
                var delBtn = el('button', {type: 'button', className: 'btn btn-secondary mr-2 d-none', textContent: S.del});
                var status = el('span', {className: 'text-muted', role: 'status'});

                var nameInput = el('input', {type: 'text', className: 'form-control mr-2', maxLength: 100, placeholder: S.name});
                nameInput.setAttribute('aria-label', S.name);
                var nameOk = el('button', {type: 'button', className: 'btn btn-primary mr-2', textContent: S.savebtn});
                var nameCancel = el('button', {type: 'button', className: 'btn btn-secondary', textContent: S.cancel});
                var namePanel = el('div', {className: 'd-none d-flex align-items-center w-100 mt-2'},
                    [nameInput, nameOk, nameCancel]);
                namePanel.classList.add('d-none');

                var confirmPanel = el('div', {className: 'd-none align-items-center w-100 mt-2'});

                [label, select, saveBtn, defBtn, delBtn, status, namePanel, confirmPanel].forEach(function(node) {
                    bar.appendChild(node);
                });
                container.insertBefore(bar, container.firstChild);

                var find = function(ref) {
                    return state.presets.filter(function(p) {
                        return p.ref === ref;
                    })[0];
                };
                var cfg = function(preset) {
                    return typeof preset.config === 'string' ? JSON.parse(preset.config) : preset.config;
                };
                var say = function(text) {
                    status.textContent = text || '';
                };

                var render = function(selected) {
                    select.textContent = '';
                    select.appendChild(el('option', {value: '', textContent: S.choose}));
                    [[S.builtin, true], [S.yours, false]].forEach(function(group) {
                        var items = state.presets.filter(function(p) {
                            return !!p.builtin === group[1];
                        });
                        if (!items.length) {
                            return;
                        }
                        var optgroup = el('optgroup', {label: group[0]});
                        items.forEach(function(p) {
                            optgroup.appendChild(el('option', {value: p.ref,
                                textContent: p.name + (p.ref === state.defaultRef ? ' ★' : '')}));
                        });
                        select.appendChild(optgroup);
                    });
                    select.value = find(selected) ? selected : '';
                    refreshButtons();
                };
                var refreshButtons = function() {
                    var ref = select.value;
                    var preset = find(ref);
                    defBtn.disabled = !preset;
                    defBtn.textContent = (preset && ref === state.defaultRef) ? S.isdefault + ' — ' + S.nodefault : S.setdefault;
                    delBtn.classList.toggle('d-none', !preset || !!preset.builtin);
                };
                var update = function(result, selected) {
                    state.presets = result.presets;
                    state.defaultRef = result.default;
                    render(selected);
                };
                var call = function(methodname, args) {
                    return Ajax.call([{methodname: methodname, args: args}])[0];
                };

                select.addEventListener('change', function() {
                    var preset = find(select.value);
                    if (preset) {
                        applyGrid(cfg(preset));
                        say(S.applied.replace('{$a}', preset.name));
                    }
                    refreshButtons();
                });

                saveBtn.addEventListener('click', function() {
                    namePanel.classList.remove('d-none');
                    nameInput.focus();
                });
                nameCancel.addEventListener('click', function() {
                    namePanel.classList.add('d-none');
                });
                var doSave = function() {
                    var name = nameInput.value.trim();
                    if (!name) {
                        nameInput.focus();
                        return;
                    }
                    call('local_quicktools_save_reviewpreset', {name: name, config: JSON.stringify(readGrid())})
                    .then(function(result) {
                        var saved = result.presets.filter(function(p) {
                            return !p.builtin && p.name === name;
                        })[0];
                        update(result, saved ? saved.ref : '');
                        namePanel.classList.add('d-none');
                        nameInput.value = '';
                        say(S.saved);
                        return;
                    }).catch(Notification.exception);
                };
                nameOk.addEventListener('click', doSave);
                nameInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        doSave();
                    } else if (e.key === 'Escape') {
                        namePanel.classList.add('d-none');
                    }
                });

                defBtn.addEventListener('click', function() {
                    var ref = select.value;
                    call('local_quicktools_default_reviewpreset', {ref: ref === state.defaultRef ? '' : ref})
                    .then(function(result) {
                        update(result, ref);
                        return;
                    }).catch(Notification.exception);
                });

                delBtn.addEventListener('click', function() {
                    var preset = find(select.value);
                    if (!preset) {
                        return;
                    }
                    confirmPanel.textContent = '';
                    var yes = el('button', {type: 'button', className: 'btn btn-danger mr-2', textContent: S.del});
                    var no = el('button', {type: 'button', className: 'btn btn-secondary', textContent: S.cancel});
                    confirmPanel.appendChild(el('span', {className: 'mr-3', textContent: S.delq.replace('{$a}', preset.name)}));
                    confirmPanel.appendChild(yes);
                    confirmPanel.appendChild(no);
                    confirmPanel.classList.remove('d-none');
                    confirmPanel.classList.add('d-flex');
                    var close = function() {
                        confirmPanel.classList.add('d-none');
                        confirmPanel.classList.remove('d-flex');
                    };
                    no.addEventListener('click', close);
                    yes.addEventListener('click', function() {
                        call('local_quicktools_delete_reviewpreset', {id: parseInt(preset.ref.slice(2), 10)})
                        .then(function(result) {
                            close();
                            update(result, '');
                            return;
                        }).catch(Notification.exception);
                    });
                });

                render(data.isnew ? state.defaultRef : '');
                // A new quiz starts from the teacher's default preset.
                if (data.isnew && find(state.defaultRef)) {
                    applyGrid(cfg(find(state.defaultRef)));
                    say(S.applied.replace('{$a}', find(state.defaultRef).name));
                }
                return;
            }).catch(Notification.exception);
        }
    };
});
