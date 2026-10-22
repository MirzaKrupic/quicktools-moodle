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
 * Strings for Quick tools.
 *
 * @package   local_quicktools
 * @copyright 2026 Bioptimizers
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['bulkmarks'] = 'Set marks for several questions';
$string['closebeforeopen'] = 'Reviews must close after they open.';
$string['enablewindow'] = 'Schedule when students can review';
$string['enablewindow_help'] = 'Moodle\'s "After the quiz is closed" review options are either on or off. With a schedule, students only get them between the two times below, and cannot review their attempt outside that window.

Choose what students can see with the "After the quiz is closed" column under Review options (or pick a preset). While the window is closed Moodle hides those options by itself and puts them back when it opens; this form always shows your choice.

The quiz needs a close date, because reviews only apply once the quiz has closed. Changes take effect within a minute.';
$string['hintclosedate'] = 'The "After the quiz is closed" column is locked until the quiz has a close date. Enable "Close the quiz" in the Timing section to use it.';
$string['hintschedule'] = 'Review window schedule is on: students get the options ticked in this column only between the open and close times below. Outside that window Moodle hides them automatically, and this form keeps showing your choice.';
$string['marksfield'] = 'Marks for the selected questions';
$string['marksinvalid'] = 'Enter a mark of 0 or more.';
$string['marksupdated'] = 'Marks updated for {$a} questions. Reloading…';
$string['needclosedate'] = 'Set a close date for the quiz first (Timing section): reviews only apply once the quiz has closed.';
$string['pickquestion'] = 'Select question';
$string['pluginname'] = 'Quick tools';
$string['preset_examafterclose'] = 'Exam: review everything after the quiz closes';
$string['preset_exammarksonly'] = 'Exam: only the attempt and marks after it closes';
$string['preset_moodledefault'] = 'Moodle default';
$string['preset_noreview'] = 'No review at all';
$string['preset_practice'] = 'Practice: everything, any time';
$string['presetapplied'] = 'Applied: {$a}';
$string['presetapply'] = 'Preset';
$string['presetbuiltin'] = 'Built-in presets';
$string['presetcancel'] = 'Cancel';
$string['presetchoose'] = 'Choose a preset…';
$string['presetdelete'] = 'Delete this preset';
$string['presetdeleteq'] = 'Delete the preset "{$a}"?';
$string['presetisdefault'] = 'This is your default for new quizzes';
$string['presetname'] = 'Preset name';
$string['presetnodefault'] = 'No default';
$string['presetsave'] = 'Save current options as a preset…';
$string['presetsavebtn'] = 'Save';
$string['presetsaved'] = 'Preset saved.';
$string['presetsetdefault'] = 'Use for new quizzes';
$string['presetyours'] = 'Your presets';
$string['privacy:metadata'] = 'Quick tools only stores quiz settings, not personal data.';
$string['reviewsclose'] = 'Reviews close';
$string['reviewsopen'] = 'Reviews open';
$string['setmarksselected'] = 'Set marks for selected';
$string['taskapply'] = 'Open and close quiz review windows';
$string['windowheading'] = 'Review window';
