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
 * Local suspend configuration and reporting helper methods.
 *
 * @package    local_suspend
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string[] Supported certificate activity module names. */
    public const CERTIFICATE_MODULES = ['customcert', 'certificate'];

    /**
     * Returns excluded course ids from plugin config.
     *
     * @return int[]
     */
    public static function get_excluded_course_ids(): array {
        $configured = (string)get_config('local_suspend', 'excludedcourses');
        if ($configured === '') {
            return [];
        }

        $courseids = preg_split('/[\s,]+/', trim($configured), -1, PREG_SPLIT_NO_EMPTY);
        if (!$courseids) {
            return [];
        }

        $courseids = array_map('intval', $courseids);
        $courseids = array_filter($courseids, static fn(int $courseid): bool => $courseid > 0);

        return array_values(array_unique($courseids));
    }

    /**
     * Stores excluded course ids in plugin config.
     *
     * @param int[] $courseids
     * @return void
     */
    public static function set_excluded_course_ids(array $courseids): void {
        $courseids = array_map('intval', $courseids);
        $courseids = array_filter($courseids, static fn(int $courseid): bool => $courseid > 0);
        $courseids = array_values(array_unique($courseids));
        sort($courseids);

        set_config('excludedcourses', implode(',', $courseids), 'local_suspend');
    }

    /**
     * Checks whether a course is excluded from automatic suspension.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_course_excluded(int $courseid): bool {
        return in_array($courseid, self::get_excluded_course_ids(), true);
    }

    /**
     * Returns course options for the exclusion form.
     *
     * @return string[]
     */
    public static function get_course_options(): array {
        global $DB;

        $courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC', 'id, shortname, fullname');
        $options = [];
        foreach ($courses as $course) {
            $options[$course->id] = format_string($course->fullname) . ' (' . s($course->shortname) . ', ID ' . $course->id . ')';
        }

        return $options;
    }

    /**
     * Returns excluded course records keyed by id.
     *
     * @return \stdClass[]
     */
    public static function get_excluded_courses(): array {
        global $DB;

        $courseids = self::get_excluded_course_ids();
        if (!$courseids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $courses = $DB->get_records_select('course', "id $insql", $params, 'fullname ASC', 'id, shortname, fullname');

        return $courses;
    }

    /**
     * Returns summary counts for the report page.
     *
     * @return array<string,int>
     */
    public static function get_course_summary(): array {
        global $DB;

        $totalcourses = $DB->count_records_select('course', 'id <> :siteid', ['siteid' => SITEID]);
        $excludedcourses = count(self::get_excluded_courses());

        return [
            'totalcourses' => $totalcourses,
            'excludedcourses' => $excludedcourses,
        ];
    }

    /**
     * Checks whether the course contains a supported certificate activity.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_has_certificate_activity(int $courseid): bool {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(self::CERTIFICATE_MODULES, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {course_modules} cm
               JOIN {modules} m
                 ON m.id = cm.module
              WHERE cm.course = :courseid
                AND m.name $insql",
            $params
        );
    }

    /**
     * Returns whether the course uses the certificate-aware workflow.
     *
     * Cached course metadata is preferred so the completion observer does not
     * repeat the same module lookup for every user finishing the same course.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_uses_certificate_workflow(int $courseid): bool {
        global $DB;

        $record = $DB->get_record('local_suspend_course_cache', [
            'courseid' => $courseid,
        ], 'hascertificateactivity', IGNORE_MISSING);
        if ($record) {
            return !empty($record->hascertificateactivity);
        }

        $hasactivity = self::course_has_certificate_activity($courseid);
        self::set_course_certificate_activity_cache($courseid, $hasactivity);

        return $hasactivity;
    }

    /**
     * Refreshes the cached certificate activity flag for courses.
     *
     * @param int[]|null $courseids
     * @return void
     */
    public static function refresh_course_certificate_activity_cache(?array $courseids = null): void {
        $isfullrefresh = ($courseids === null);

        $courseids = self::normalise_course_ids_for_cache($courseids);
        if (!$courseids) {
            if ($isfullrefresh) {
                self::delete_stale_course_certificate_cache([]);
            }
            return;
        }

        $courseidswithactivity = self::get_course_ids_with_certificate_activity($courseids);
        foreach ($courseids as $courseid) {
            self::set_course_certificate_activity_cache($courseid, isset($courseidswithactivity[$courseid]));
        }

        if ($isfullrefresh) {
            self::delete_stale_course_certificate_cache($courseids);
        }
    }

    /**
     * Marks that the user completed the course.
     *
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    public static function mark_course_completed(int $courseid, int $userid): void {
        self::upsert_signal($courseid, $userid, ['coursecompleted' => 1]);
    }

    /**
     * Marks that the user received a certificate in the course.
     *
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    public static function mark_certificate_issued(int $courseid, int $userid): void {
        self::upsert_signal($courseid, $userid, ['certificateissued' => 1]);
    }

    /**
     * Checks whether both required signals have been received.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function is_ready_to_suspend(int $courseid, int $userid): bool {
        global $DB;

        $record = $DB->get_record('local_suspend_state', [
            'courseid' => $courseid,
            'userid' => $userid,
        ], 'coursecompleted, certificateissued', IGNORE_MISSING);

        if (!$record) {
            return false;
        }

        return !empty($record->coursecompleted) && !empty($record->certificateissued);
    }

    /**
     * Removes any stored suspension signals for the user and course.
     *
     * Once both signals have been consumed, retaining them can cause a later
     * completion cycle to reuse a stale certificate-issued flag.
     *
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    public static function clear_suspend_state(int $courseid, int $userid): void {
        global $DB;

        $DB->delete_records('local_suspend_state', [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);
    }

    /**
     * Upserts a suspension signal row.
     *
     * @param int $courseid
     * @param int $userid
     * @param array<string,int> $fields
     * @return void
     */
    private static function upsert_signal(int $courseid, int $userid, array $fields): void {
        global $DB;

        $existing = $DB->get_record('local_suspend_state', [
            'courseid' => $courseid,
            'userid' => $userid,
        ], '*', IGNORE_MISSING);
        $now = time();

        if ($existing) {
            foreach ($fields as $field => $value) {
                $existing->{$field} = $value;
            }
            $existing->timemodified = $now;
            $DB->update_record('local_suspend_state', $existing);
            return;
        }

        $record = (object)[
            'courseid' => $courseid,
            'userid' => $userid,
            'coursecompleted' => 0,
            'certificateissued' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        foreach ($fields as $field => $value) {
            $record->{$field} = $value;
        }

        $DB->insert_record('local_suspend_state', $record);
    }

    /**
     * Returns the set of course ids that currently contain a supported certificate activity.
     *
     * @param int[] $courseids
     * @return array<int,int>
     */
    private static function get_course_ids_with_certificate_activity(array $courseids): array {
        global $DB;

        [$moduleinsql, $moduleparams] = $DB->get_in_or_equal(self::CERTIFICATE_MODULES, SQL_PARAMS_NAMED, 'module');
        [$courseinsql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');

        return $DB->get_records_sql_menu(
            "SELECT DISTINCT cm.course, cm.course
               FROM {course_modules} cm
               JOIN {modules} m
                 ON m.id = cm.module
              WHERE cm.course $courseinsql
                AND m.name $moduleinsql",
            array_merge($courseparams, $moduleparams)
        );
    }

    /**
     * Normalises course ids before writing cache rows.
     *
     * @param int[]|null $courseids
     * @return int[]
     */
    private static function normalise_course_ids_for_cache(?array $courseids): array {
        global $DB;

        if ($courseids === null) {
            return array_map('intval', array_keys(
                $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], '', 'id')
            ));
        }

        $courseids = array_map('intval', $courseids);
        $courseids = array_filter($courseids, static fn(int $courseid): bool => $courseid > 0 && $courseid !== SITEID);

        return array_values(array_unique($courseids));
    }

    /**
     * Stores the cached certificate activity flag for a course.
     *
     * @param int $courseid
     * @param bool $hasactivity
     * @return void
     */
    private static function set_course_certificate_activity_cache(int $courseid, bool $hasactivity): void {
        global $DB;

        $existing = $DB->get_record('local_suspend_course_cache', [
            'courseid' => $courseid,
        ], '*', IGNORE_MISSING);
        $now = time();

        if ($existing) {
            if ((int)$existing->hascertificateactivity === (int)$hasactivity) {
                return;
            }

            $existing->hascertificateactivity = (int)$hasactivity;
            $existing->timemodified = $now;
            $DB->update_record('local_suspend_course_cache', $existing);
            return;
        }

        $DB->insert_record('local_suspend_course_cache', (object)[
            'courseid' => $courseid,
            'hascertificateactivity' => (int)$hasactivity,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Removes cache rows for courses that no longer exist.
     *
     * @param int[] $validcourseids
     * @return void
     */
    private static function delete_stale_course_certificate_cache(array $validcourseids): void {
        global $DB;

        $cachedcourseids = array_map('intval', array_keys(
            $DB->get_records('local_suspend_course_cache', [], '', 'courseid')
        ));
        $stalecourseids = array_values(array_diff($cachedcourseids, $validcourseids));
        if (!$stalecourseids) {
            return;
        }

        $DB->delete_records_list('local_suspend_course_cache', 'courseid', $stalecourseids);
    }
}
