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

/**
 * Privacy provider for Quick tools.
 *
 * The only personal data is each teacher's saved review option presets and their default-preset choice.
 *
 * @package   local_quicktools
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quicktools\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_quicktools\local\review_presets;

/**
 * Privacy API implementation.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\userlist_provider {
    /**
     * Describe the data stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_quicktools_revpreset', [
            'userid' => 'privacy:metadata:revpreset:userid',
            'name' => 'privacy:metadata:revpreset:name',
            'config' => 'privacy:metadata:revpreset:config',
            'timecreated' => 'privacy:metadata:revpreset:timecreated',
            'timemodified' => 'privacy:metadata:revpreset:timemodified',
        ], 'privacy:metadata:revpreset');
        $collection->add_user_preference(review_presets::PREF, 'privacy:metadata:preference:default');
        return $collection;
    }

    /**
     * Presets are held at site level.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if (
            $DB->record_exists('local_quicktools_revpreset', ['userid' => $userid])
                || get_user_preferences(review_presets::PREF, null, $userid) !== null
        ) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Export a user's presets and default.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $system = \context_system::instance();
        if (!in_array($system->id, $contextlist->get_contextids())) {
            return;
        }
        $presets = [];
        foreach ($DB->get_records('local_quicktools_revpreset', ['userid' => $userid], 'name ASC') as $row) {
            $presets[] = (object) ['name' => $row->name, 'config' => json_decode($row->config, true),
                'timecreated' => \core_privacy\local\request\transform::datetime($row->timecreated),
                'timemodified' => \core_privacy\local\request\transform::datetime($row->timemodified)];
        }
        writer::with_context($system)->export_data(
            [get_string('pluginname', 'local_quicktools')],
            (object) ['review_option_presets' => $presets]
        );
        $default = get_user_preferences(review_presets::PREF, null, $userid);
        if ($default !== null) {
            writer::export_user_preference(
                'local_quicktools',
                review_presets::PREF,
                $default,
                get_string('privacy:metadata:preference:default', 'local_quicktools')
            );
        }
    }

    /**
     * Delete everyone's presets (site context).
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('local_quicktools_revpreset');
            $DB->delete_records('user_preferences', ['name' => review_presets::PREF]);
        }
    }

    /**
     * Delete one user's presets.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        if (in_array(\context_system::instance()->id, $contextlist->get_contextids())) {
            $DB->delete_records('local_quicktools_revpreset', ['userid' => $userid]);
            unset_user_preference(review_presets::PREF, $userid);
        }
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT DISTINCT userid FROM {local_quicktools_revpreset}', []);
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {user_preferences} WHERE name = :name',
            ['name' => review_presets::PREF]
        );
    }

    /**
     * Delete data for several users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_quicktools_revpreset', "userid $insql", $params);
        $DB->delete_records_select(
            'user_preferences',
            "name = :name AND userid $insql",
            ['name' => review_presets::PREF] + $params
        );
    }
}
