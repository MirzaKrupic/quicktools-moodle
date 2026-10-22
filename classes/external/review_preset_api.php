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

/**
 * Web services behind the review preset bar on the quiz settings page.
 *
 * @package   local_quicktools
 * @copyright 2026 Bioptimizers
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quicktools\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_quicktools\local\review_presets;

/**
 * Save, delete and default-select review presets. Presets belong to the calling teacher.
 */
class review_preset_api extends external_api {

    /**
     * Only people who can manage quizzes somewhere may use presets.
     */
    protected static function check_access(): int {
        global $USER;
        self::validate_context(\context_system::instance());
        if (!get_user_capability_course('mod/quiz:manage', $USER->id, false, '', '', 1)) {
            throw new \required_capability_exception(\context_system::instance(), 'mod/quiz:manage', 'nopermissions', '');
        }
        return (int) $USER->id;
    }

    /**
     * What every call returns: the refreshed list.
     *
     * @return external_single_structure
     */
    public static function returns_structure(): external_single_structure {
        return new external_single_structure([
            'presets' => new external_multiple_structure(new external_single_structure([
                'ref' => new external_value(PARAM_RAW, 'b:<key> or u:<id>'),
                'name' => new external_value(PARAM_RAW, 'Display name'),
                'config' => new external_value(PARAM_RAW, 'JSON grid'),
                'builtin' => new external_value(PARAM_BOOL, 'Shipped with the plugin'),
            ])),
            'default' => new external_value(PARAM_RAW, 'Default preset reference or empty'),
        ]);
    }

    /**
     * Refreshed list in return format.
     *
     * @param int $userid
     * @return array
     */
    protected static function listing(int $userid): array {
        $page = review_presets::for_page($userid);
        foreach ($page['presets'] as &$preset) {
            $preset['config'] = json_encode($preset['config']);
        }
        return $page;
    }

    /**
     * Parameters for save.
     *
     * @return external_function_parameters
     */
    public static function save_parameters(): external_function_parameters {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'Preset name'),
            'config' => new external_value(PARAM_RAW, 'JSON grid: moment => option => 0/1'),
        ]);
    }

    /**
     * Save the current grid as a preset.
     *
     * @param string $name
     * @param string $config
     * @return array
     */
    public static function save(string $name, string $config): array {
        $params = self::validate_parameters(self::save_parameters(), ['name' => $name, 'config' => $config]);
        $userid = self::check_access();
        $grid = json_decode($params['config'], true);
        if (!is_array($grid)) {
            throw new \invalid_parameter_exception('Bad preset data.');
        }
        review_presets::save($userid, $params['name'], $grid);
        return self::listing($userid);
    }

    /**
     * Return structure for save.
     *
     * @return external_single_structure
     */
    public static function save_returns(): external_single_structure {
        return self::returns_structure();
    }

    /**
     * Parameters for delete.
     *
     * @return external_function_parameters
     */
    public static function delete_parameters(): external_function_parameters {
        return new external_function_parameters(['id' => new external_value(PARAM_INT, 'Preset id')]);
    }

    /**
     * Delete one of the teacher's presets.
     *
     * @param int $id
     * @return array
     */
    public static function delete(int $id): array {
        $params = self::validate_parameters(self::delete_parameters(), ['id' => $id]);
        $userid = self::check_access();
        review_presets::delete($userid, $params['id']);
        return self::listing($userid);
    }

    /**
     * Return structure for delete.
     *
     * @return external_single_structure
     */
    public static function delete_returns(): external_single_structure {
        return self::returns_structure();
    }

    /**
     * Parameters for set_default.
     *
     * @return external_function_parameters
     */
    public static function set_default_parameters(): external_function_parameters {
        return new external_function_parameters(['ref' => new external_value(PARAM_RAW, 'Preset reference, empty to clear')]);
    }

    /**
     * Choose the preset applied to every new quiz.
     *
     * @param string $ref
     * @return array
     */
    public static function set_default(string $ref): array {
        $params = self::validate_parameters(self::set_default_parameters(), ['ref' => $ref]);
        $userid = self::check_access();
        review_presets::set_default($userid, $params['ref']);
        return self::listing($userid);
    }

    /**
     * Return structure for set_default.
     *
     * @return external_single_structure
     */
    public static function set_default_returns(): external_single_structure {
        return self::returns_structure();
    }
}
