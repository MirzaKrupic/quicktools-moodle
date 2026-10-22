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
 * Review window logic (quiz review schedule).
 *
 * The quiz keeps its "After the quiz is closed" review options. Outside the window we clear those bits
 * and inside the window we put back the ones the teacher chose, so every Moodle screen (reports, app,
 * gradebook) follows the schedule without extra code.
 *
 * @package   local_quicktools
 * @copyright 2026 Bioptimizers
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quicktools\local;

defined('MOODLE_INTERNAL') || die();

use mod_quiz\question\display_options;

/**
 * Stores windows and applies them to quizzes.
 */
class review_window {
    /** @var string[] Quiz columns holding review options. */
    const FIELDS = ['reviewattempt', 'reviewcorrectness', 'reviewmarks', 'reviewspecificfeedback',
        'reviewgeneralfeedback', 'reviewrightanswer', 'reviewoverallfeedback'];

    /**
     * The window record for a quiz, or false.
     *
     * @param int $quizid
     * @return \stdClass|false
     */
    public static function get(int $quizid) {
        global $DB;
        return $DB->get_record('local_quicktools_reviewwin', ['quizid' => $quizid]);
    }

    /**
     * Which "after the quiz is closed" options are currently set on the quiz row.
     *
     * @param \stdClass $quiz
     * @return int[] field => 0/1
     */
    public static function current_bits(\stdClass $quiz): array {
        $bits = [];
        foreach (self::FIELDS as $field) {
            $bits[$field] = ((int) $quiz->$field & display_options::AFTER_CLOSE) ? 1 : 0;
        }
        return $bits;
    }

    /**
     * Should reviews be visible at this moment?
     *
     * @param \stdClass $window
     * @param int $now
     * @return string 'open' or 'closed'
     */
    public static function desired_state(\stdClass $window, int $now): string {
        $started = !$window->opentime || $now >= $window->opentime;
        $notended = !$window->closetime || $now < $window->closetime;
        return ($started && $notended) ? 'open' : 'closed';
    }

    /**
     * Options to pre-tick for a quiz that has no window yet: what it already shows after closing, or a sensible set.
     *
     * @param \stdClass|null $quiz
     * @return int[] column => 0/1
     */
    public static function default_mask(?\stdClass $quiz): array {
        $bits = $quiz ? self::current_bits($quiz) : [];
        if (!array_sum($bits)) {
            $bits = array_fill_keys(self::FIELDS, 0);
            $bits['reviewattempt'] = $bits['reviewcorrectness'] = $bits['reviewmarks'] = 1;
        }
        return $bits;
    }

    /**
     * Create, update or remove the window for a quiz from the settings form.
     *
     * @param int $quizid
     * @param bool $enabled
     * @param int $opentime
     * @param int $closetime
     * @param int[] $mask column => 0/1, which review options students get while the window is open
     */
    public static function save(int $quizid, bool $enabled, int $opentime, int $closetime, array $mask = []): void {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $window = self::get($quizid);

        if (!$enabled) {
            if ($window) {
                // Leave the chosen options on the quiz's own "After the quiz is closed" settings, then forget the window.
                $window->opentime = 0;
                $window->closetime = 0;
                self::apply($window, $quiz, 'open');
                $DB->delete_records('local_quicktools_reviewwin', ['id' => $window->id]);
            }
            return;
        }

        $clean = [];
        foreach (self::FIELDS as $field) {
            $clean[$field] = !empty($mask[$field]) ? 1 : 0;
        }
        if (!$clean['reviewattempt']) {
            // Without "the attempt" nothing else can be shown.
            $clean = array_fill_keys(self::FIELDS, 0);
        }

        if (!$window) {
            $window = (object) ['quizid' => $quizid, 'state' => ''];
        }
        $window->mask = json_encode($clean);
        $window->opentime = $opentime;
        $window->closetime = $closetime;
        $window->timemodified = time();
        if (!empty($window->id)) {
            $DB->update_record('local_quicktools_reviewwin', $window);
        } else {
            $window->id = $DB->insert_record('local_quicktools_reviewwin', $window);
        }

        self::apply($window, $quiz);
    }

    /**
     * Put one quiz into the state its window calls for.
     *
     * @param \stdClass $window
     * @param \stdClass $quiz
     * @param string|null $force state to use instead of working it out from the clock
     * @return bool true if anything changed on the quiz
     */
    public static function apply(\stdClass $window, \stdClass $quiz, ?string $force = null): bool {
        global $DB;

        $state = $force ?? self::desired_state($window, time());
        $mask = json_decode($window->mask ?? '', true) ?: [];

        $update = new \stdClass();
        foreach (self::FIELDS as $field) {
            $value = (int) $quiz->$field & ~display_options::AFTER_CLOSE;
            if ($state === 'open' && !empty($mask[$field])) {
                $value |= display_options::AFTER_CLOSE;
            }
            if ($value !== (int) $quiz->$field) {
                $update->$field = $value;
            }
        }

        $changed = (array) $update !== [];
        if ($changed) {
            $update->id = $quiz->id;
            $update->timemodified = time();
            $DB->update_record('quiz', $update);
        }
        if (!empty($window->id) && $window->state !== $state) {
            $DB->set_field('local_quicktools_reviewwin', 'state', $state, ['id' => $window->id]);
        }
        return $changed;
    }

    /**
     * Apply every window. Called by the scheduled task.
     *
     * @return int number of quizzes changed
     */
    public static function apply_all(): int {
        global $DB;

        $changed = 0;
        foreach ($DB->get_records('local_quicktools_reviewwin') as $window) {
            $quiz = $DB->get_record('quiz', ['id' => $window->quizid]);
            if (!$quiz) {
                $DB->delete_records('local_quicktools_reviewwin', ['id' => $window->id]);
                continue;
            }
            if (self::apply($window, $quiz)) {
                $changed++;
            }
        }
        return $changed;
    }
}
