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

    /** @var string[][] Quiz column => [string identifier, component] for the option names students see. */
    const LABELS = [
        'reviewattempt' => ['theattempt', 'quiz'],
        'reviewcorrectness' => ['whethercorrect', 'question'],
        'reviewmarks' => ['marks', 'quiz'],
        'reviewspecificfeedback' => ['specificfeedback', 'question'],
        'reviewgeneralfeedback' => ['generalfeedback', 'question'],
        'reviewrightanswer' => ['rightanswer', 'question'],
        'reviewoverallfeedback' => ['reviewoverallfeedback', 'quiz'],
    ];

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
     * Create, update or remove the window for a quiz from the settings form.
     *
     * The teacher's choice is whatever is ticked in the form's "After the quiz is closed" column. While the
     * window is closed Moodle shows them cleared in the DB, so the form is pre-filled from the saved choice.
     *
     * @param int $quizid
     * @param bool $enabled
     * @param int $opentime
     * @param int $closetime
     */
    public static function save(int $quizid, bool $enabled, int $opentime, int $closetime): void {
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

        $mask = self::current_bits($quiz);
        if (!$mask['reviewattempt']) {
            // Without "the attempt" nothing else can be shown.
            $mask = array_fill_keys(self::FIELDS, 0);
        }

        if (!$window) {
            $window = (object) ['quizid' => $quizid, 'state' => ''];
        }
        $window->mask = json_encode($mask);
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

    /**
     * What to tell students on the quiz page about the review window.
     *
     * Reviews only start once the quiz itself has closed, so the first moment is the later of the two.
     *
     * @param \stdClass $quiz
     * @param int|null $now
     * @return array|null type (info, success, secondary), title and details, or null if there is nothing to say
     */
    public static function notice(\stdClass $quiz, ?int $now = null): ?array {
        $window = self::get((int) $quiz->id);
        if (!$window) {
            return null;
        }
        $mask = json_decode($window->mask ?? '', true) ?: [];
        if (empty($mask['reviewattempt'])) {
            return null;
        }

        $now = $now ?? time();
        $open = max((int) $window->opentime, (int) $quiz->timeclose);
        $close = (int) $window->closetime;
        $format = get_string('strftimedaydatetime', 'langconfig');
        $a = (object) ['open' => userdate($open, $format), 'close' => $close ? userdate($close, $format) : ''];

        if ($close && $now >= $close) {
            return ['type' => 'secondary', 'title' => get_string('reviewnotice_ended', 'local_quicktools', $a), 'details' => ''];
        }
        if ($now >= $open) {
            $type = 'success';
            $title = get_string($close ? 'reviewnotice_open' : 'reviewnotice_open_nolimit', 'local_quicktools', $a);
        } else {
            $type = 'info';
            $title = get_string($close ? 'reviewnotice_soon' : 'reviewnotice_soon_nolimit', 'local_quicktools', $a);
        }

        $names = [];
        foreach (self::LABELS as $column => [$identifier, $component]) {
            if (!empty($mask[$column])) {
                $names[] = get_string($identifier, $component);
            }
        }
        $details = get_string('reviewnotice_what', 'local_quicktools', implode(', ', $names));
        return ['type' => $type, 'title' => $title, 'details' => $details];
    }
}
