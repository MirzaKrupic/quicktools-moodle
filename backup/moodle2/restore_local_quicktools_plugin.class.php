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
 * Restore of the quiz review window.
 *
 * @package   local_quicktools
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Brings the review window back on the restored or copied quiz.
 */
class restore_local_quicktools_plugin extends restore_local_plugin {
    /** @var stdClass|null Window data read from the backup, held until the quiz exists. */
    protected $pending = null;

    /**
     * Module-level paths.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [new restore_path_element('local_quicktools_reviewwindow', $this->get_pathfor('/reviewwindow'))];
    }

    /**
     * The module step runs before the quiz is created, so only remember the data here.
     *
     * @param array $data
     */
    public function process_local_quicktools_reviewwindow($data) {
        $this->pending = (object) $data;
    }

    /**
     * Called once the whole activity is restored: create the window for the new quiz.
     */
    public function after_restore_module() {
        global $DB;

        if (!$this->pending) {
            return;
        }
        $quizid = (int) $this->task->get_activityid();
        $quiz = $quizid ? $DB->get_record('quiz', ['id' => $quizid]) : false;
        if (!$quiz || $DB->record_exists('local_quicktools_reviewwin', ['quizid' => $quizid])) {
            return;
        }

        $window = (object) [
            'quizid' => $quizid,
            'opentime' => $this->apply_date_offset($this->pending->opentime),
            'closetime' => $this->apply_date_offset($this->pending->closetime),
            'mask' => $this->pending->mask,
            'state' => '',
            'timemodified' => time(),
        ];
        $window->id = $DB->insert_record('local_quicktools_reviewwin', $window);
        // The backup may have been taken while the options were hidden; set them for the current time.
        \local_quicktools\local\review_window::apply($window, $quiz);
        $this->pending = null;
    }
}
