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
 * Privacy class for requesting user data.
 *
 * @package    profilefield_checkbox
 * @copyright  2018 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace profilefield_checkbox\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\approved_contextlist;

/**
 * Privacy class for requesting user data.
 *
 * @package    profilefield_checkbox
 * @copyright  2018 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table('user_info_data', [
            'userid' => 'privacy:metadata:profilefield_checkbox:userid',
            'fieldid' => 'privacy:metadata:profilefield_checkbox:fieldid',
            'data' => 'privacy:metadata:profilefield_checkbox:data',
            'dataformat' => 'privacy:metadata:profilefield_checkbox:dataformat'
        ], 'privacy:metadata:profilefield_checkbox:tableexplanation');
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int         $userid     The user to search.
     * @return  contextlist $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $sql = "SELECT ctx.id
                FROM {user_info_data} uda
                JOIN {user_info_field} uif
                    ON uda.fieldid = uif.id
                JOIN {context} ctx
                    ON ctx.instanceid = uda.userid
                        AND ctx.contextlevel = :contextlevel
                WHERE uda.userid = :userid
                    AND uif.datatype = :datatype";
        $params = [
            'userid' => $userid,
            'contextlevel' => CONTEXT_USER,
            'datatype' => 'checkbox'
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $results = static::get_records($contextlist->get_user()->id);
        foreach ($results as $result) {
            $data = (object) [
                'name' => $result->name,
                'description' => $result->description,
                'data' => $result->data
            ];
            \core_privacy\local\request\writer::with_context($contextlist->current())->export_data([
                get_string('pluginname', 'profilefield_checkbox')], $data);
        }
    }

    /**
     * Delete all use data which matches the specified deletion_criteria.
     *
     * @param   context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // Delete data only for user context.
        if ($context->contextlevel == CONTEXT_USER) {
            static::delete_data($context->instanceid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        static::delete_data($contextlist->get_user()->id);
    }

    /**
     * Delete data related to a userid.
     *
     * @param  int $userid The user ID
     */
    protected static function delete_data($userid) {
        global $DB;

        $params = [
            'userid' => $userid,
            'datatype' => 'checkbox'
        ];

        $DB->delete_records_select('user_info_data', "fieldid IN (
                SELECT id FROM {user_info_field} WHERE datatype = :datatype)
                AND userid = :userid", $params);
    }

    /**
     * Get records related to this plugin and user.
     *
     * @param  int $userid The user ID
     * @return array An array of records.
     */
    protected static function get_records($userid) {
        global $DB;

        $sql = "SELECT *
                FROM {user_info_data} uda
                JOIN {user_info_field} uif
                    ON uda.fieldid = uif.id
                WHERE uda.userid = :userid
                    AND uif.datatype = :datatype";
        $params = [
            'userid' => $userid,
            'datatype' => 'checkbox'
        ];

        return $DB->get_records_sql($sql, $params);
    }
}