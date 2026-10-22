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
 * Set the maximum mark of several quiz slots in one go.
 *
 * @package   local_quicktools
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quicktools\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Same steps as the single-question "edit maximum mark" action in the quiz editor,
 * but the grades are recalculated once for the whole batch.
 */
class set_slot_marks extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Quiz course module id'),
            'slotids' => new external_multiple_structure(new external_value(PARAM_INT, 'Slot id')),
            'maxmark' => new external_value(PARAM_FLOAT, 'New maximum mark for every listed slot'),
        ]);
    }

    /**
     * Update the marks.
     *
     * @param int $cmid
     * @param int[] $slotids
     * @param float $maxmark
     * @return array
     */
    public static function execute(int $cmid, array $slotids, float $maxmark): array {
        global $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'slotids' => $slotids, 'maxmark' => $maxmark]
        );

        $quizobj = \mod_quiz\quiz_settings::create_for_cmid($params['cmid']);
        self::validate_context($quizobj->get_context());
        require_capability('mod/quiz:manage', $quizobj->get_context());

        if ($params['maxmark'] < 0 || !$params['slotids']) {
            throw new \invalid_parameter_exception('Marks must be 0 or more and at least one question is required.');
        }

        $structure = $quizobj->get_structure();
        $quiz = $quizobj->get_quiz();
        $gradecalculator = $quizobj->get_grade_calculator();

        $transaction = $DB->start_delegated_transaction();
        $changed = false;
        foreach (array_unique($params['slotids']) as $slotid) {
            // Throws if the slot is not part of this quiz.
            $slot = $structure->get_slot_by_id($slotid);
            $changed = $structure->update_slot_maxmark($slot, $params['maxmark']) || $changed;
        }
        $transaction->allow_commit();

        if ($changed) {
            quiz_delete_previews($quiz);
            $gradecalculator->recompute_quiz_sumgrades();
            $gradecalculator->recompute_all_attempt_sumgrades();
            $gradecalculator->recompute_all_final_grades();
            quiz_update_grades($quiz, 0, true);
        }

        return [
            'updated' => count(array_unique($params['slotids'])),
            'newsummarks' => quiz_format_grade($quiz, $quiz->sumgrades),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'updated' => new external_value(PARAM_INT, 'Number of questions processed'),
            'newsummarks' => new external_value(PARAM_RAW, 'Formatted total marks of the quiz'),
        ]);
    }
}
