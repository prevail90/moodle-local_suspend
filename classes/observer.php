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

namespace local_suspend;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer callbacks.
 *
 * @package    local_suspend
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Suspends a completed student's enrolments in the completed course.
     *
     * @param \core\event\course_completed $event
     * @return void
     */
    public static function course_completed(\core\event\course_completed $event): void {
        $courseid = (int)($event->courseid ?? ($event->other['courseid'] ?? 0));
        $userid = (int)($event->relateduserid ?? ($event->other['relateduserid'] ?? $event->userid));

        if (!$courseid || !$userid || manager::is_course_excluded($courseid)) {
            return;
        }

        if (!manager::course_uses_certificate_workflow($courseid)) {
            self::suspend_course_enrolments_if_student($courseid, $userid);
            return;
        }

        manager::mark_course_completed($courseid, $userid);

        self::suspend_if_ready($courseid, $userid);
    }

    /**
     * Suspends a completed student's enrolments when a custom certificate has been issued.
     *
     * @param \mod_customcert\event\issue_created $event
     * @return void
     */
    public static function customcert_issue_created(\mod_customcert\event\issue_created $event): void {
        $userid = (int)($event->relateduserid ?? $event->userid);
        $cmid = (int)$event->contextinstanceid;

        if (!$userid || !$cmid) {
            return;
        }

        [, $cm] = get_course_and_cm_from_cmid($cmid);
        if ($cm->modname !== 'customcert' || manager::is_course_excluded((int)$cm->course)) {
            return;
        }

        manager::mark_certificate_issued((int)$cm->course, $userid);
        self::suspend_if_ready((int)$cm->course, $userid);
    }

    /**
     * Queues a deferred certificate issue check after the certificate module view flow completes.
     *
     * The legacy mod_certificate plugin issues the certificate after it triggers its view event,
     * so the actual issue record has to be checked asynchronously.
     *
     * @param \mod_certificate\event\course_module_viewed $event
     * @return void
     */
    public static function certificate_course_module_viewed(\mod_certificate\event\course_module_viewed $event): void {
        $userid = (int)($event->relateduserid ?? $event->userid);
        $cmid = (int)$event->contextinstanceid;

        if (!$userid || !$cmid) {
            return;
        }

        $task = new \local_suspend\task\process_certificate_view_task();
        $task->set_component('local_suspend');
        $task->set_custom_data([
            'cmid' => $cmid,
            'userid' => $userid,
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    public static function suspend_if_ready(int $courseid, int $userid): void {
        if (!manager::is_ready_to_suspend($courseid, $userid)) {
            return;
        }

        self::suspend_course_enrolments_if_student($courseid, $userid);
        manager::clear_suspend_state($courseid, $userid);
    }

    /**
     * Suspends all active enrolments in a course for users holding a student-archetype role.
     *
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    private static function suspend_course_enrolments_if_student(int $courseid, int $userid): void {
        global $DB;

        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            return;
        }

        $studentroles = $DB->get_records('role', ['archetype' => 'student'], '', 'id');
        if (!$studentroles) {
            return;
        }

        // Only process users who hold a student-archetype role in this course context or a parent context.
        $studentroleids = array_map('intval', array_keys($studentroles));
        $userroles = get_user_roles($coursecontext, $userid, true);
        foreach ($userroles as $roleassignment) {
            if (in_array((int)$roleassignment->roleid, $studentroleids, true)) {
                self::suspend_course_enrolments($courseid, $userid);
                return;
            }
        }
    }

    /**
     * Suspends all active enrolments for the user in the course.
     *
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    private static function suspend_course_enrolments(int $courseid, int $userid): void {
        global $DB;

        $instances = enrol_get_instances($courseid, true);
        if (!$instances) {
            return;
        }

        foreach ($instances as $instance) {
            $ue = $DB->get_record('user_enrolments', [
                'enrolid' => $instance->id,
                'userid' => $userid,
            ]);

            if (!$ue || (int)$ue->status === ENROL_USER_SUSPENDED) {
                continue;
            }

            $plugin = enrol_get_plugin($instance->enrol);
            if (!$plugin) {
                continue;
            }

            $plugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
        }
    }
}
