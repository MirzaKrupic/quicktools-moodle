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
 * Backup of the quiz review window, so copying or backing up a course keeps the schedule.
 *
 * @package   local_quicktools
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the review window to a quiz's backup.
 */
class backup_local_quicktools_plugin extends backup_local_plugin {
    /**
     * Module-level structure: only quizzes have a review window.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../modulename', 'quiz');

        $wrapper = new backup_nested_element($this->get_recommended_name());
        $window = new backup_nested_element('reviewwindow', ['id'], ['opentime', 'closetime', 'mask', 'state']);

        $plugin->add_child($wrapper);
        $wrapper->add_child($window);
        $window->set_source_table('local_quicktools_reviewwin', ['quizid' => backup::VAR_ACTIVITYID]);

        return $plugin;
    }
}
