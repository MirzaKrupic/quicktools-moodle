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
 * Quick tools library callbacks: footer JavaScript and the quiz review window form fields.
 *
 * @package   local_quicktools
 * @copyright 2026 Bioptimizers
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_quicktools\local\review_window;

/**
 * Legacy footer callback, used by Moodle before 4.4 (and still honoured by later versions).
 * Newer versions reach the same code through db/hooks.php.
 */
function local_quicktools_before_footer() {
    \local_quicktools\local\injector::inject();
}

/**
 * Add the "Review window" section to the quiz settings form.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_quicktools_coursemodule_standard_elements($formwrapper, $mform) {
    $current = $formwrapper->get_current();
    if (empty($current->modulename) || $current->modulename !== 'quiz') {
        return;
    }

    $mform->addElement('header', 'local_qrw_hdr', get_string('windowheading', 'local_quicktools'));
    $mform->addElement('advcheckbox', 'local_qrw_enabled', get_string('enablewindow', 'local_quicktools'));
    $mform->addHelpButton('local_qrw_enabled', 'enablewindow', 'local_quicktools');
    $mform->addElement('date_time_selector', 'local_qrw_open', get_string('reviewsopen', 'local_quicktools'),
        ['optional' => true]);
    $mform->addElement('date_time_selector', 'local_qrw_close', get_string('reviewsclose', 'local_quicktools'),
        ['optional' => true]);
    $mform->hideIf('local_qrw_open', 'local_qrw_enabled', 'notchecked');
    $mform->hideIf('local_qrw_close', 'local_qrw_enabled', 'notchecked');

    $window = !empty($current->instance) ? review_window::get((int) $current->instance) : false;
    if ($window) {
        $mform->setDefault('local_qrw_enabled', 1);
        $mform->setDefault('local_qrw_open', (int) $window->opentime);
        $mform->setDefault('local_qrw_close', (int) $window->closetime);
    }
}

/**
 * Check the window dates.
 *
 * @param moodleform_mod $formwrapper
 * @param array $data
 * @return array errors keyed by field
 */
function local_quicktools_coursemodule_validation($formwrapper, $data) {
    $errors = [];
    if (!empty($data['local_qrw_enabled']) && empty($data['timeclose'])) {
        $errors['local_qrw_enabled'] = get_string('needclosedate', 'local_quicktools');
    }
    if (!empty($data['local_qrw_enabled']) && !empty($data['local_qrw_open']) && !empty($data['local_qrw_close'])
            && $data['local_qrw_close'] <= $data['local_qrw_open']) {
        $errors['local_qrw_close'] = get_string('closebeforeopen', 'local_quicktools');
    }
    return $errors;
}

/**
 * Save the window after the quiz itself has been saved.
 *
 * @param stdClass $data module info
 * @param stdClass $course
 * @return stdClass
 */
function local_quicktools_coursemodule_edit_post_actions($data, $course) {
    if (empty($data->modulename) || $data->modulename !== 'quiz' || empty($data->instance)) {
        return $data;
    }
    review_window::save((int) $data->instance, !empty($data->local_qrw_enabled),
        (int) ($data->local_qrw_open ?? 0), (int) ($data->local_qrw_close ?? 0));
    return $data;
}
