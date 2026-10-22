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
 * @copyright  2026 Mirza Krupic
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Show the review window notice on the quiz page.
 *
 * @param {Object} notice type (alert style), title and optional details
 */
export const init = notice => {
    const box = document.createElement('div');
    box.className = 'alert alert-' + notice.type + ' local-quicktools-reviewnotice';
    box.setAttribute('role', 'status');
    const title = document.createElement('strong');
    title.textContent = notice.title;
    box.appendChild(title);
    if (notice.details) {
        const details = document.createElement('div');
        details.textContent = notice.details;
        box.appendChild(details);
    }

    const info = document.querySelector('.quizinfo');
    if (info && info.parentNode) {
        info.parentNode.insertBefore(box, info.nextSibling);
        return;
    }
    const main = document.querySelector('[role="main"]');
    if (main) {
        main.insertBefore(box, main.firstChild);
    }
};
