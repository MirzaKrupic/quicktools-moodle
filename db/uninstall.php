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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Uninstall: give quizzes their review options back before the plugin's data is removed.
 *
 * @package   local_quicktools
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * While a review window is closed the plugin keeps the quiz's "After the quiz is closed" options cleared.
 * Put each quiz's chosen options back so nothing stays hidden after the plugin is gone.
 *
 * @return bool
 */
function xmldb_local_quicktools_uninstall() {
    global $DB;

    foreach ($DB->get_records('local_quicktools_reviewwin') as $window) {
        $quiz = $DB->get_record('quiz', ['id' => $window->quizid]);
        if ($quiz) {
            \local_quicktools\local\review_window::apply($window, $quiz, 'open');
        }
    }
    $DB->delete_records('user_preferences', ['name' => \local_quicktools\local\review_presets::PREF]);
    return true;
}
