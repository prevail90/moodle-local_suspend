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
    /** @var string[] Course modules treated as certificate activities. */
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
        $certificatecourses = count(self::get_courses_with_certificate_requirement());

        return [
            'totalcourses' => $totalcourses,
            'excludedcourses' => $excludedcourses,
            'certificatecourses' => $certificatecourses,
            'coursecompletiononly' => max(0, $totalcourses - $certificatecourses),
        ];
    }

    /**
     * Returns course ids containing a supported certificate activity.
     *
     * @return int[]
     */
    public static function get_courses_with_certificate_requirement(): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(self::CERTIFICATE_MODULES, SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT cm.course
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name $insql
                   AND cm.course <> :siteid";
        $params['siteid'] = SITEID;

        $records = $DB->get_records_sql($sql, $params);

        return array_map('intval', array_keys($records));
    }

    /**
     * Checks if a course has a supported certificate activity.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_has_certificate_requirement(int $courseid): bool {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(self::CERTIFICATE_MODULES, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :courseid
                AND m.name $insql",
            $params
        );
    }
}
