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

namespace local_quicktools\external;

use core_external\external_api;

/**
 * Tests for the bulk quiz marks web service.
 *
 * @package   local_quicktools
 * @category  test
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_quicktools\external\set_slot_marks
 */
final class set_slot_marks_test extends \advanced_testcase {
    /**
     * A quiz with three questions, a teacher and a student.
     *
     * @return array quiz record, cmid, slot ids, teacher, student
     */
    protected function setup_quiz(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $questions = $generator->get_plugin_generator('core_question');
        $category = $questions->create_question_category();
        foreach (['shortanswer', 'truefalse', 'numerical'] as $type) {
            $question = $questions->create_question($type, null, ['category' => $category->id]);
            quiz_add_quiz_question($question->id, $quiz);
        }
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        $slots = array_keys($DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot'));
        return [$quiz, $quiz->cmid, $slots, $generator->create_and_enrol($course, 'editingteacher'),
            $generator->create_and_enrol($course, 'student')];
    }

    /**
     * Run the service and clean the result like the AJAX layer does.
     *
     * @param int $cmid
     * @param int[] $slotids
     * @param float $marks
     * @return array
     */
    protected function run_service(int $cmid, array $slotids, float $marks): array {
        return external_api::clean_returnvalue(
            set_slot_marks::execute_returns(),
            set_slot_marks::execute($cmid, $slotids, $marks)
        );
    }

    public function test_sets_several_questions_and_recalculates_once(): void {
        global $DB;
        [$quiz, $cmid, $slots, $teacher] = $this->setup_quiz();
        $this->setUser($teacher);
        $this->assertEquals(3, $DB->get_field('quiz', 'sumgrades', ['id' => $quiz->id]));

        $result = $this->run_service($cmid, [$slots[0], $slots[1]], 4.5);

        $this->assertSame(2, $result['updated']);
        $this->assertEquals(4.5, $DB->get_field('quiz_slots', 'maxmark', ['id' => $slots[0]]));
        $this->assertEquals(4.5, $DB->get_field('quiz_slots', 'maxmark', ['id' => $slots[1]]));
        $this->assertEquals(1, $DB->get_field('quiz_slots', 'maxmark', ['id' => $slots[2]]), 'untouched');
        $this->assertEquals(10, $DB->get_field('quiz', 'sumgrades', ['id' => $quiz->id]));
        $this->assertStringContainsString('10', $result['newsummarks']);
    }

    public function test_existing_attempts_are_updated(): void {
        global $DB;
        [$quiz, $cmid, $slots, $teacher, $student] = $this->setup_quiz();
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour('deferredfeedback');
        $attempt = quiz_create_attempt($quizobj, 1, false, time(), false, $student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, time());
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        $this->setUser($teacher);
        $this->run_service($cmid, [$slots[0]], 7);

        $this->assertEquals(7, $DB->get_field_sql(
            'SELECT qa.maxmark FROM {question_attempts} qa JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
              WHERE quiza.quiz = ? AND qa.slot = 1',
            [$quiz->id]
        ));
    }

    public function test_zero_is_allowed_negative_is_not(): void {
        global $DB;
        [, $cmid, $slots, $teacher] = $this->setup_quiz();
        $this->setUser($teacher);
        $this->run_service($cmid, [$slots[0]], 0);
        $this->assertEquals(0, $DB->get_field('quiz_slots', 'maxmark', ['id' => $slots[0]]));

        $this->expectException(\invalid_parameter_exception::class);
        $this->run_service($cmid, [$slots[0]], -1);
    }

    public function test_needs_at_least_one_question(): void {
        [, $cmid, , $teacher] = $this->setup_quiz();
        $this->setUser($teacher);
        $this->expectException(\invalid_parameter_exception::class);
        $this->run_service($cmid, [], 2);
    }

    public function test_cannot_touch_a_slot_of_another_quiz(): void {
        global $DB;
        [, $cmid, $slots, $teacher] = $this->setup_quiz();
        $this->setUser($teacher);
        $otherslot = $DB->get_field('quiz_slots', 'MAX(id)', []) + 100;
        $this->expectException(\coding_exception::class);
        $this->run_service($cmid, [$slots[0], $otherslot], 3);
    }

    public function test_students_are_refused(): void {
        global $DB;
        [, $cmid, $slots, , $student] = $this->setup_quiz();
        $this->setUser($student);
        try {
            $this->run_service($cmid, [$slots[0]], 9);
            $this->fail('A student changed marks');
        } catch (\moodle_exception $e) {
            $this->assertEquals(1, $DB->get_field('quiz_slots', 'maxmark', ['id' => $slots[0]]));
        }
    }
}
