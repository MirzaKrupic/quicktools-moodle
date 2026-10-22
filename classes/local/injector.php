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
 * Loads the quick tools JavaScript on the pages that need it.
 *
 * @package   local_quicktools
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quicktools\local;

/**
 * Decides which tools apply to the current page.
 */
class injector {
    /** @var bool Guards against running twice when both the hook and the legacy callback fire. */
    protected static $done = false;

    /**
     * Hook entry point.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function inject_from_hook($hook): void {
        self::inject();
    }

    /**
     * Add the JavaScript for the current page, if any tool applies.
     */
    public static function inject(): void {
        global $DB, $PAGE, $USER;

        if (self::$done || !isloggedin() || isguestuser()) {
            return;
        }
        self::$done = true;

        // Keep the scroll position when the edit mode switch is used.
        if ($PAGE->user_allowed_editing()) {
            $PAGE->requires->js_call_amd('local_quicktools/editmode_scroll', 'init');
        }

        // Review option presets on the quiz settings form.
        if ($PAGE->pagetype === 'mod-quiz-mod' && has_capability('moodle/course:manageactivities', $PAGE->context)) {
            $isnew = optional_param('add', '', PARAM_ALPHANUMEXT) === 'quiz';
            // Only small data goes in the page itself; the presets are fetched by the script.
            $data = ['isnew' => $isnew, 'windowmask' => null];
            // A quiz with a review schedule shows the saved choice, not what is hidden at this moment.
            $cmid = optional_param('update', 0, PARAM_INT);
            $quizid = $cmid ? $DB->get_field('course_modules', 'instance', ['id' => $cmid]) : 0;
            $window = $quizid ? review_window::get((int) $quizid) : false;
            if ($window) {
                $data['windowmask'] = json_decode($window->mask ?? '', true) ?: null;
            }
            $PAGE->requires->js_call_amd('local_quicktools/review_presets', 'init', [$data]);
        }

        // Tell students about the review window on the quiz page.
        $cm = $PAGE->cm;
        if ($PAGE->pagetype === 'mod-quiz-view' && $cm) {
            $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
            $notice = $quiz ? review_window::notice($quiz) : null;
            if ($notice) {
                $PAGE->requires->js_call_amd('local_quicktools/review_notice', 'init', [$notice]);
            }
        }

        // Bulk marks on the quiz Questions page.
        if ($PAGE->pagetype === 'mod-quiz-edit' && $cm && has_capability('mod/quiz:manage', $PAGE->context)) {
            $PAGE->requires->js_call_amd('local_quicktools/quiz_bulkmarks', 'init', [(int) $cm->id]);
        }
    }
}
