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
 * Scheduled task that opens and closes quiz reviews on time.
 *
 * @package   local_quicktools
 * @copyright 2026 Bioptimizers
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quicktools\task;

defined('MOODLE_INTERNAL') || die();

use local_quicktools\local\review_window;

/**
 * Runs every minute and applies any window whose open or close time has passed.
 */
class apply_review_windows extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskapply', 'local_quicktools');
    }

    /**
     * Apply all windows.
     */
    public function execute() {
        $changed = review_window::apply_all();
        if ($changed) {
            mtrace("Quiz review window: updated $changed quiz(zes).");
        }
    }
}
