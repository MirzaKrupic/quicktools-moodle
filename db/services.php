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
 * Web service functions (used by the page JavaScript only).
 *
 * @package   local_quicktools
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_quicktools_set_slot_marks' => [
        'classname' => \local_quicktools\external\set_slot_marks::class,
        'description' => 'Set the maximum mark of several quiz questions at once.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/quiz:manage',
    ],
    'local_quicktools_save_reviewpreset' => [
        'classname' => \local_quicktools\external\review_preset_api::class,
        'methodname' => 'save',
        'description' => 'Save the quiz review options grid as a named preset.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_quicktools_delete_reviewpreset' => [
        'classname' => \local_quicktools\external\review_preset_api::class,
        'methodname' => 'delete',
        'description' => 'Delete one of the user\'s review option presets.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_quicktools_default_reviewpreset' => [
        'classname' => \local_quicktools\external\review_preset_api::class,
        'methodname' => 'set_default',
        'description' => 'Choose the review preset applied to new quizzes.',
        'type' => 'write',
        'ajax' => true,
    ],
];
