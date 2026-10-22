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

namespace local_quicktools\local;

use mod_quiz\question\display_options;

/**
 * Tests for the quiz review window.
 *
 * @package   local_quicktools
 * @category  test
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_quicktools\local\review_window
 */
final class review_window_test extends \advanced_testcase {
    /**
     * Make a quiz whose "After the quiz is closed" options are the attempt and marks.
     *
     * @return \stdClass quiz record
     */
    protected function make_quiz(): \stdClass {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'timeclose' => time() - DAYSECS]);
        $this->set_after_close($quiz->id, ['reviewattempt', 'reviewmarks']);
        return $DB->get_record('quiz', ['id' => $quiz->id]);
    }

    /**
     * Tick exactly these "After the quiz is closed" options, leaving the other moments alone.
     *
     * @param int $quizid
     * @param string[] $columns
     */
    protected function set_after_close(int $quizid, array $columns): void {
        global $DB;
        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        foreach (review_window::fields() as $field) {
            $value = (int) $quiz->$field & ~display_options::AFTER_CLOSE;
            if (in_array($field, $columns)) {
                $value |= display_options::AFTER_CLOSE;
            }
            $DB->set_field('quiz', $field, $value, ['id' => $quizid]);
        }
    }

    /**
     * Which options are ticked after close, as column names.
     *
     * @param int $quizid
     * @return string[]
     */
    protected function after_close(int $quizid): array {
        global $DB;
        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        return array_keys(array_filter(review_window::current_bits($quiz)));
    }

    public function test_desired_state(): void {
        $window = (object) ['opentime' => 1000, 'closetime' => 2000];
        $this->assertSame('closed', review_window::desired_state($window, 999));
        $this->assertSame('open', review_window::desired_state($window, 1000));
        $this->assertSame('open', review_window::desired_state($window, 1999));
        $this->assertSame('closed', review_window::desired_state($window, 2000));

        $nostart = (object) ['opentime' => 0, 'closetime' => 2000];
        $this->assertSame('open', review_window::desired_state($nostart, 5));
        $noend = (object) ['opentime' => 1000, 'closetime' => 0];
        $this->assertSame('open', review_window::desired_state($noend, PHP_INT_MAX));
        $this->assertSame('closed', review_window::desired_state($noend, 10));
    }

    public function test_fields_match_the_moodle_version(): void {
        global $DB;
        $this->resetAfterTest();
        $fields = review_window::fields();
        $this->assertContains('reviewattempt', $fields);
        $this->assertContains('reviewmarks', $fields);
        $this->assertSame($DB->get_manager()->field_exists('quiz', 'reviewmaxmarks'), in_array('reviewmaxmarks', $fields));
        foreach ($fields as $field) {
            $this->assertTrue($DB->get_manager()->field_exists('quiz', $field), $field);
        }
    }

    public function test_saving_a_future_window_hides_the_options_and_remembers_them(): void {
        global $DB;
        $quiz = $this->make_quiz();
        $now = time();

        review_window::save($quiz->id, true, $now + HOURSECS, $now + 2 * HOURSECS);

        $this->assertSame([], $this->after_close($quiz->id));
        $window = review_window::get($quiz->id);
        $this->assertSame('closed', $window->state);
        $mask = json_decode($window->mask, true);
        $this->assertSame(['reviewattempt', 'reviewmarks'], array_keys(array_filter($mask)));
    }

    public function test_options_come_back_when_the_window_opens(): void {
        global $DB;
        $quiz = $this->make_quiz();
        $now = time();
        review_window::save($quiz->id, true, $now + HOURSECS, $now + 2 * HOURSECS);

        // The window opens (time passes).
        $DB->set_field('local_quicktools_reviewwin', 'opentime', $now - 60);
        $this->assertSame(1, review_window::apply_all());

        $this->assertSame(['reviewattempt', 'reviewmarks'], $this->after_close($quiz->id));
        $this->assertSame('open', review_window::get($quiz->id)->state);
        // Nothing further to do on the next run.
        $this->assertSame(0, review_window::apply_all());

        // The window ends.
        $DB->set_field('local_quicktools_reviewwin', 'closetime', $now - 1);
        $this->assertSame(1, review_window::apply_all());
        $this->assertSame([], $this->after_close($quiz->id));
        $this->assertSame('closed', review_window::get($quiz->id)->state);
    }

    public function test_the_form_shows_the_saved_choice_and_resaving_keeps_it(): void {
        $quiz = $this->make_quiz();
        $now = time();
        review_window::save($quiz->id, true, $now + HOURSECS, $now + 2 * HOURSECS);

        // The settings form pre-fills the column from the saved choice, so the teacher saves it back unchanged.
        $this->set_after_close($quiz->id, ['reviewattempt', 'reviewmarks']);
        review_window::save($quiz->id, true, $now + HOURSECS, $now + 3 * HOURSECS);
        $this->assertSame([], $this->after_close($quiz->id), 'still hidden: the window has not opened');
        $this->assertSame(['reviewattempt', 'reviewmarks'], array_keys(array_filter(
            json_decode(review_window::get($quiz->id)->mask, true)
        )));

        // A new choice made on the form replaces the old one.
        $this->set_after_close($quiz->id, ['reviewattempt', 'reviewrightanswer']);
        review_window::save($quiz->id, true, $now - 60, $now + HOURSECS);
        $this->assertSame(['reviewattempt', 'reviewrightanswer'], $this->after_close($quiz->id));
    }

    public function test_without_the_attempt_nothing_can_be_shown(): void {
        $quiz = $this->make_quiz();
        $this->set_after_close($quiz->id, ['reviewmarks']);
        review_window::save($quiz->id, true, time() - 60, time() + HOURSECS);
        $this->assertSame([], $this->after_close($quiz->id));
    }

    public function test_turning_the_schedule_off_gives_the_options_back(): void {
        global $DB;
        $quiz = $this->make_quiz();
        review_window::save($quiz->id, true, time() + HOURSECS, time() + 2 * HOURSECS);
        $this->assertSame([], $this->after_close($quiz->id));

        review_window::save($quiz->id, false, 0, 0);

        $this->assertSame(['reviewattempt', 'reviewmarks'], $this->after_close($quiz->id));
        $this->assertFalse(review_window::get($quiz->id));
        $this->assertSame(0, $DB->count_records('local_quicktools_reviewwin'));
    }

    public function test_windows_of_deleted_quizzes_are_cleaned_up(): void {
        global $DB;
        $quiz = $this->make_quiz();
        review_window::save($quiz->id, true, time() + HOURSECS, time() + 2 * HOURSECS);
        $DB->delete_records('quiz', ['id' => $quiz->id]);
        review_window::apply_all();
        $this->assertSame(0, $DB->count_records('local_quicktools_reviewwin'));
    }

    public function test_student_notice(): void {
        $quiz = $this->make_quiz();
        $now = time();
        $this->assertNull(review_window::notice($quiz), 'no window, no notice');

        review_window::save($quiz->id, true, $now + DAYSECS, $now + 2 * DAYSECS);
        $quiz = \core\di::get(\moodle_database::class)->get_record('quiz', ['id' => $quiz->id]);

        $soon = review_window::notice($quiz, $now);
        $this->assertSame('info', $soon['type']);
        $this->assertStringContainsString('will be able to review', $soon['title']);
        $this->assertStringContainsString('The attempt', $soon['details']);
        $this->assertStringContainsString('Marks', $soon['details']);

        $open = review_window::notice($quiz, $now + DAYSECS + 60);
        $this->assertSame('success', $open['type']);
        $this->assertStringContainsString('Review is open', $open['title']);

        $ended = review_window::notice($quiz, $now + 3 * DAYSECS);
        $this->assertSame('secondary', $ended['type']);
        $this->assertStringContainsString('ended', $ended['title']);
        $this->assertSame('', $ended['details']);
    }

    public function test_no_notice_when_the_attempt_is_hidden(): void {
        global $DB;
        $quiz = $this->make_quiz();
        $DB->insert_record('local_quicktools_reviewwin', (object) ['quizid' => $quiz->id, 'opentime' => 0, 'closetime' => 0,
            'mask' => json_encode(['reviewattempt' => 0, 'reviewmarks' => 1]), 'state' => '', 'timemodified' => time()]);
        $this->assertNull(review_window::notice($quiz));
    }

    public function test_review_is_blocked_outside_the_window(): void {
        global $DB;
        $quiz = $this->make_quiz();
        $now = time();
        review_window::save($quiz->id, true, $now + HOURSECS, $now + 2 * HOURSECS);
        $quizrecord = $DB->get_record('quiz', ['id' => $quiz->id]);
        $closed = display_options::make_from_quiz($quizrecord, display_options::AFTER_CLOSE);
        $this->assertEmpty($closed->attempt);

        $DB->set_field('local_quicktools_reviewwin', 'opentime', $now - 60);
        review_window::apply_all();
        $quizrecord = $DB->get_record('quiz', ['id' => $quiz->id]);
        $open = display_options::make_from_quiz($quizrecord, display_options::AFTER_CLOSE);
        $this->assertNotEmpty($open->attempt);
    }

    public function test_uninstall_restores_the_options_of_closed_windows(): void {
        global $CFG;
        $quiz = $this->make_quiz();
        review_window::save($quiz->id, true, time() + HOURSECS, time() + 2 * HOURSECS);
        $this->assertSame([], $this->after_close($quiz->id));

        require_once($CFG->dirroot . '/local/quicktools/db/uninstall.php');
        $this->assertTrue(xmldb_local_quicktools_uninstall());

        $this->assertSame(['reviewattempt', 'reviewmarks'], $this->after_close($quiz->id));
    }

    public function test_scheduled_task_applies_windows(): void {
        global $DB;
        $quiz = $this->make_quiz();
        review_window::save($quiz->id, true, time() + HOURSECS, time() + 2 * HOURSECS);
        $DB->set_field('local_quicktools_reviewwin', 'opentime', time() - 60);

        $task = new \local_quicktools\task\apply_review_windows();
        $this->assertNotEmpty($task->get_name());
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame(['reviewattempt', 'reviewmarks'], $this->after_close($quiz->id));
    }

    public function test_form_validation(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/quicktools/lib.php');
        $this->assertSame([], local_quicktools_coursemodule_validation(null, ['local_qrw_enabled' => 0, 'timeclose' => 0]));
        $this->assertArrayHasKey(
            'local_qrw_enabled',
            local_quicktools_coursemodule_validation(null, ['local_qrw_enabled' => 1, 'timeclose' => 0])
        );
        $this->assertArrayHasKey('local_qrw_close', local_quicktools_coursemodule_validation(
            null,
            ['local_qrw_enabled' => 1, 'timeclose' => 5, 'local_qrw_open' => 200, 'local_qrw_close' => 100]
        ));
        $this->assertSame([], local_quicktools_coursemodule_validation(
            null,
            ['local_qrw_enabled' => 1, 'timeclose' => 5, 'local_qrw_open' => 100, 'local_qrw_close' => 200]
        ));
    }

    public function test_duplicating_a_quiz_copies_the_window(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->setAdminUser();
        $quiz = $this->make_quiz();
        $now = time();
        review_window::save($quiz->id, true, $now - HOURSECS, $now + DAYSECS);
        $course = get_course($quiz->course);

        $cmid = get_coursemodule_from_instance('quiz', $quiz->id)->id;
        $new = duplicate_module($course, get_fast_modinfo($course)->get_cm($cmid));

        $copy = review_window::get((int) $new->instance);
        $this->assertNotFalse($copy);
        $original = review_window::get($quiz->id);
        $this->assertEquals($original->opentime, $copy->opentime);
        $this->assertEquals($original->closetime, $copy->closetime);
        $this->assertSame($original->mask, $copy->mask);
        $this->assertSame(['reviewattempt', 'reviewmarks'], $this->after_close((int) $new->instance), 'window open: options on');
        $this->assertSame(2, $DB->count_records('local_quicktools_reviewwin'));
    }

    public function test_duplicating_other_activities_creates_no_window(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->setAdminUser();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        duplicate_module($course, get_fast_modinfo($course)->get_cm($page->cmid));
        $this->assertSame(0, $DB->count_records('local_quicktools_reviewwin'));
    }
}
