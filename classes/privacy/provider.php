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

namespace local_suspend\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy API provider for local_suspend.
 *
 * @package    local_suspend
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
    /**
     * Describes the personal data stored by this plugin.
     *
     * @param \core_privacy\local\metadata\collection $collection
     * @return \core_privacy\local\metadata\collection
     */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $collection->add_database_table('local_suspend_state', [
            'courseid' => 'privacy:metadata:local_suspend_state:courseid',
            'userid' => 'privacy:metadata:local_suspend_state:userid',
            'coursecompleted' => 'privacy:metadata:local_suspend_state:coursecompleted',
            'certificateissued' => 'privacy:metadata:local_suspend_state:certificateissued',
        ], 'privacy:metadata:local_suspend_state');

        return $collection;
    }

    /**
     * Returns the contexts containing data for the user.
     *
     * @param int $userid
     * @return \core_privacy\local\request\contextlist
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_suspend_state} lss
                    ON lss.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel
                   AND lss.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Exports user data for approved contexts.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ((int)$context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $record = $DB->get_record('local_suspend_state', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ], 'coursecompleted, certificateissued, timecreated, timemodified', IGNORE_MISSING);
            if (!$record) {
                continue;
            }

            \core_privacy\local\request\writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_suspend')],
                $record
            );
        }
    }

    /**
     * Deletes all user data for a course context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ((int)$context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $DB->delete_records('local_suspend_state', ['courseid' => $context->instanceid]);
    }

    /**
     * Deletes user data for approved contexts.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ((int)$context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $DB->delete_records('local_suspend_state', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Adds users with stored data to the supplied userlist.
     *
     * @param \core_privacy\local\request\userlist $userlist
     * @return void
     */
    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist): void {
        if ((int)$userlist->get_context()->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $userlist->add_from_sql('userid', "SELECT userid
                                             FROM {local_suspend_state}
                                            WHERE courseid = :courseid", [
            'courseid' => $userlist->get_context()->instanceid,
        ]);
    }

    /**
     * Deletes data for a list of users in a course context.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist): void {
        global $DB;

        if ((int)$userlist->get_context()->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$usersql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $userlist->get_context()->instanceid;
        $DB->delete_records_select('local_suspend_state', "courseid = :courseid AND userid $usersql", $params);
    }
}
