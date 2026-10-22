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
 * Keeps your place on the page when the Edit mode switch is used.
 *
 * Before the switch reloads the page we remember the element at the top of the
 * screen (a section, activity or quiz question) and how far down it was. After
 * the reload we scroll that element back to the same spot.
 *
 * @module     local_quicktools/editmode_scroll
 * @copyright  2026 Mirza Krupic
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const KEY = 'local_quicktools_scroll';
const MAXAGE = 30000;
const ANCHORS = '[id^="section-"], [id^="module-"], [id^="slot-"], [id^="question-"]';
const SWITCH = 'input[data-pageurl][data-context], #editingswitch';

const store = function(value) {
    try {
        if (value === null) {
            window.sessionStorage.removeItem(KEY);
        } else {
            window.sessionStorage.setItem(KEY, JSON.stringify(value));
        }
    } catch (e) {
        // Storage blocked: the page just behaves as it always did.
    }
};

const load = function() {
    try {
        return JSON.parse(window.sessionStorage.getItem(KEY));
    } catch (e) {
        return null;
    }
};

/**
 * Page identity: path plus the id parameter, so a hash or other parameters do not matter.
 *
 * @param {String} href
 * @return {String}
 */
const pageKey = function(href) {
    const url = new URL(href, window.location.href);
    return url.pathname + '?' + (url.searchParams.get('id') || '');
};

/**
 * Moodle 4.x scrolls an inner container (#page) rather than the window; fall back to the window.
 *
 * @return {Element}
 */
const scroller = function() {
    const page = document.getElementById('page');
    if (page && page.scrollHeight > page.clientHeight
            && /(auto|scroll)/.test(window.getComputedStyle(page).overflowY)) {
        return page;
    }
    return document.scrollingElement || document.documentElement;
};

const headerBottom = function() {
    const nav = document.querySelector('nav.fixed-top, .navbar.fixed-top');
    return nav ? nav.getBoundingClientRect().bottom : 0;
};

/**
 * Remember where we are so the next load of the same page can come back here.
 *
 * @param {String} [targetHref] address the page is about to load, defaults to this page
 */
const saveState = function(targetHref) {
    const bound = headerBottom() + 1;
    let straddling = null;
    let below = null;
    document.querySelectorAll(ANCHORS).forEach(function(el) {
        const rect = el.getBoundingClientRect();
        if (!rect.height) {
            return;
        }
        if (rect.top <= bound && rect.bottom > bound) {
            straddling = el; // Last one in document order is the deepest.
        } else if (rect.top > bound && !below) {
            below = el;
        }
    });
    const anchor = straddling || below;
    store({
        page: pageKey(targetHref || window.location.href),
        id: anchor ? anchor.id : null,
        offset: anchor ? anchor.getBoundingClientRect().top : 0,
        y: scroller().scrollTop,
        time: Date.now()
    });
};

const restore = function() {
    const saved = load();
    if (!saved) {
        return;
    }
    store(null);
    if (Date.now() - saved.time > MAXAGE || saved.page !== pageKey(window.location.href)) {
        return;
    }

    let moved = false;
    ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach(function(type) {
        window.addEventListener(type, function() {
            moved = true;
        }, {once: true, passive: true, capture: true});
    });

    const apply = function() {
        if (moved) {
            return;
        }
        const container = scroller();
        const el = saved.id ? document.getElementById(saved.id) : null;
        if (el) {
            container.scrollTop += el.getBoundingClientRect().top - saved.offset;
        } else {
            container.scrollTop = saved.y;
        }
    };
    apply();
    // Images and editing controls change the layout after the first paint, so correct once more.
    if (document.readyState === 'complete') {
        window.setTimeout(apply, 300);
    } else {
        window.addEventListener('load', function() {
            window.setTimeout(apply, 100);
        }, {once: true});
    }
};

export {saveState};

/**
 * Restore the saved position (if any) and start remembering it when the Edit mode switch is used.
 */
export const init = () => {
    restore();
    document.addEventListener('change', e => {
        if (e.target && e.target.matches && e.target.matches(SWITCH)) {
            saveState(e.target.dataset.pageurl);
        }
    }, true);
};
