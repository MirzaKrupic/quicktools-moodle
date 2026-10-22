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
 * Review option presets: built-in ones plus the ones each teacher saves.
 *
 * A preset is the whole "Review options" grid of the quiz settings form: for each moment
 * (during, immediately, open, closed) which of the seven options are ticked.
 *
 * @package   local_quicktools
 * @copyright 2026 Bioptimizers
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quicktools\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Presets storage and built-ins.
 */
class review_presets {
    /** @var string[] Moments in the grid, in form order. */
    const WHEN = ['during', 'immediately', 'open', 'closed'];
    /** @var string[] Options in the grid. */
    const OPTIONS = ['attempt', 'correctness', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback'];
    /** @var string User preference holding the default preset reference. */
    const PREF = 'local_quicktools_review_default';

    /**
     * Build a grid from a list of ticked options per moment.
     *
     * @param array $ticked moment => option names, or the string 'all'
     * @return array moment => option => 0/1
     */
    protected static function grid(array $ticked): array {
        $grid = [];
        foreach (self::WHEN as $when) {
            $on = $ticked[$when] ?? [];
            foreach (self::OPTIONS as $option) {
                $grid[$when][$option] = ($on === 'all' || in_array($option, $on)) ? 1 : 0;
            }
        }
        return $grid;
    }

    /**
     * Presets that ship with the plugin.
     *
     * @return array[] each with ref, name, config (grid)
     */
    public static function builtin(): array {
        $presets = [
            'moodledefault' => ['during' => ['attempt'], 'immediately' => 'all', 'open' => 'all', 'closed' => ['attempt']],
            'practice' => ['during' => ['attempt'], 'immediately' => 'all', 'open' => 'all', 'closed' => 'all'],
            'examafterclose' => ['during' => ['attempt'], 'closed' => 'all'],
            'exammarksonly' => ['during' => ['attempt'], 'closed' => ['attempt', 'marks']],
            'noreview' => ['during' => ['attempt']],
        ];
        $out = [];
        foreach ($presets as $key => $ticked) {
            $out[] = ['ref' => 'b:' . $key, 'name' => get_string('preset_' . $key, 'local_quicktools'),
                'config' => self::grid($ticked), 'builtin' => true];
        }
        return $out;
    }

    /**
     * The presets a teacher saved.
     *
     * @param int $userid
     * @return array[] each with ref, name, config (grid)
     */
    public static function for_user(int $userid): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('local_quicktools_revpreset', ['userid' => $userid], 'name ASC') as $row) {
            $out[] = ['ref' => 'u:' . $row->id, 'name' => $row->name, 'config' => json_decode($row->config, true) ?: [],
                'builtin' => false];
        }
        return $out;
    }

    /**
     * Keep only known moments and options, as 0/1.
     *
     * @param array $config
     * @return array
     */
    public static function clean(array $config): array {
        $clean = [];
        foreach (self::WHEN as $when) {
            foreach (self::OPTIONS as $option) {
                $clean[$when][$option] = !empty($config[$when][$option]) ? 1 : 0;
            }
        }
        return $clean;
    }

    /**
     * Save a preset under a name, replacing the teacher's own preset with that name.
     *
     * @param int $userid
     * @param string $name
     * @param array $config
     */
    public static function save(int $userid, string $name, array $config): void {
        global $DB;
        $name = trim(\core_text::substr($name, 0, 100));
        if ($name === '') {
            throw new \invalid_parameter_exception('A preset needs a name.');
        }
        $json = json_encode(self::clean($config));
        $existing = $DB->get_record('local_quicktools_revpreset', ['userid' => $userid, 'name' => $name]);
        if ($existing) {
            $DB->update_record('local_quicktools_revpreset',
                (object) ['id' => $existing->id, 'config' => $json, 'timemodified' => time()]);
        } else {
            $DB->insert_record('local_quicktools_revpreset', (object) ['userid' => $userid, 'name' => $name,
                'config' => $json, 'timecreated' => time(), 'timemodified' => time()]);
        }
    }

    /**
     * Delete one of the teacher's presets (and clear it as default if it was).
     *
     * @param int $userid
     * @param int $id
     */
    public static function delete(int $userid, int $id): void {
        global $DB;
        if ($DB->delete_records('local_quicktools_revpreset', ['id' => $id, 'userid' => $userid])
                && get_user_preferences(self::PREF, '', $userid) === 'u:' . $id) {
            unset_user_preference(self::PREF, $userid);
        }
    }

    /**
     * The teacher's default preset reference ('b:...' or 'u:...'), or ''.
     *
     * @param int $userid
     * @return string
     */
    public static function get_default(int $userid): string {
        return (string) get_user_preferences(self::PREF, '', $userid);
    }

    /**
     * Set or clear the default preset.
     *
     * @param int $userid
     * @param string $ref preset reference, '' to clear
     */
    public static function set_default(int $userid, string $ref): void {
        global $DB;
        if ($ref === '') {
            unset_user_preference(self::PREF, $userid);
            return;
        }
        $valid = false;
        if (preg_match('/^b:(\w+)$/', $ref, $m)) {
            $valid = in_array($ref, array_column(self::builtin(), 'ref'));
        } else if (preg_match('/^u:(\d+)$/', $ref, $m)) {
            $valid = $DB->record_exists('local_quicktools_revpreset', ['id' => $m[1], 'userid' => $userid]);
        }
        if (!$valid) {
            throw new \invalid_parameter_exception('Unknown preset.');
        }
        set_user_preference(self::PREF, $ref, $userid);
    }

    /**
     * Everything the page JavaScript needs.
     *
     * @param int $userid
     * @return array
     */
    public static function for_page(int $userid): array {
        return ['presets' => array_merge(self::builtin(), self::for_user($userid)), 'default' => self::get_default($userid)];
    }
}
