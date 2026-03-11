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

        if (!self::has_completed_certificate_requirement($courseid, $userid)) {
            return;
        }

        self::suspend_course_enrolments_if_student($courseid, $userid);
    }

    /**
     * Suspends a completed student's enrolments when a certificate module completion happens after course completion.
     *
     * @param \core\event\course_module_completion_updated $event
     * @return void
     */
    public static function course_module_completion_updated(
        \core\event\course_module_completion_updated $event
    ): void {
        global $DB;

        $userid = (int)($event->relateduserid ?? 0);
        $cmcid = (int)$event->objectid;

        if (!$userid || !$cmcid) {
            return;
        }

        $cmcompletion = $DB->get_record('course_modules_completion', ['id' => $cmcid]);
        if (!$cmcompletion || (int)$cmcompletion->completionstate === COMPLETION_INCOMPLETE) {
            return;
        }

        [, $cm] = get_course_and_cm_from_cmid($cmcompletion->coursemoduleid);
        if (manager::is_course_excluded($cm->course) || !in_array($cm->modname, manager::CERTIFICATE_MODULES, true)) {
            return;
        }

        if (!self::is_course_completed($cm->course, $userid)) {
            return;
        }

        if (!self::has_completed_certificate_requirement($cm->course, $userid)) {
            return;
        }

        self::suspend_course_enrolments_if_student($cm->course, $userid);
    }

    /**
     * Checks whether the course has a completed certificate activity for the user.
     *
     * Courses without certificate modules keep the original behaviour.
     * Certificate activities must have completion tracking enabled for the prerequisite to be meaningful.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    private static function has_completed_certificate_requirement(int $courseid, int $userid): bool {
        $modinfo = get_fast_modinfo($courseid, $userid);
        $foundcertificate = false;

        foreach ($modinfo->get_cms() as $cm) {
            if (!in_array($cm->modname, manager::CERTIFICATE_MODULES, true)) {
                continue;
            }

            $foundcertificate = true;

            if (self::has_issued_certificate($cm, $userid)) {
                return true;
            }
        }

        return !$foundcertificate;
    }

    /**
     * Checks if the user has completed the course.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    private static function is_course_completed(int $courseid, int $userid): bool {
        global $DB;

        $completion = $DB->get_record('course_completions', [
            'course' => $courseid,
            'userid' => $userid,
        ], 'timecompleted', IGNORE_MISSING);

        return !empty($completion->timecompleted);
    }

    /**
     * Checks whether a supported certificate module has actually issued a certificate to the user.
     *
     * @param \cm_info $cm
     * @param int $userid
     * @return bool
     */
    private static function has_issued_certificate(\cm_info $cm, int $userid): bool {
        global $DB;

        $dbman = $DB->get_manager();

        if ($cm->modname === 'customcert') {
            $table = new \xmldb_table('customcert_issues');
            if (!$dbman->table_exists($table)) {
                return false;
            }

            return $DB->record_exists('customcert_issues', [
                'customcertid' => $cm->instance,
                'userid' => $userid,
            ]);
        }

        if ($cm->modname === 'certificate') {
            $table = new \xmldb_table('certificate_issues');
            if (!$dbman->table_exists($table)) {
                return false;
            }

            return $DB->record_exists('certificate_issues', [
                'certificateid' => $cm->instance,
                'userid' => $userid,
            ]);
        }

        return false;
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
