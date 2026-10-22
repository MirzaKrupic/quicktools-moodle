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
 * Shows students when they can review their quiz attempt (the review window), on the quiz page.
 *
 * @module     local_quicktools/review_notice
 * @copyright  2026 Bioptimizers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        init: function(notice) {
            var box = document.createElement('div');
            box.className = 'alert alert-' + notice.type + ' local-quicktools-reviewnotice';
            box.setAttribute('role', 'status');
            var title = document.createElement('strong');
            title.textContent = notice.title;
            box.appendChild(title);
            if (notice.details) {
                var details = document.createElement('div');
                details.textContent = notice.details;
                box.appendChild(details);
            }

            var info = document.querySelector('.quizinfo');
            if (info && info.parentNode) {
                info.parentNode.insertBefore(box, info.nextSibling);
                return;
            }
            var main = document.querySelector('[role="main"]');
            if (main) {
                main.insertBefore(box, main.firstChild);
            }
        }
    };
});
