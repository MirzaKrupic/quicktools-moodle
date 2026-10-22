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
 * @copyright 2026 Bioptimizers
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quicktools\local;

defined('MOODLE_INTERNAL') || die();

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
        global $PAGE;

        if (self::$done || !isloggedin() || isguestuser()) {
            return;
        }
        self::$done = true;

        // Keep the scroll position when the edit mode switch is used.
        if ($PAGE->user_allowed_editing()) {
            $PAGE->requires->js_call_amd('local_quicktools/editmode_scroll', 'init');
        }

        // Bulk marks on the quiz Questions page.
        $cm = $PAGE->cm;
        if ($PAGE->pagetype === 'mod-quiz-edit' && $cm && has_capability('mod/quiz:manage', $PAGE->context)) {
            $PAGE->requires->js_call_amd('local_quicktools/quiz_bulkmarks', 'init', [(int) $cm->id]);
        }
    }
}
