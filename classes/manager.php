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
    /** @var bool Default course-level enablement. */
    private const DEFAULT_ENABLED = true;

    /** @var bool Default certificate wait behavior. */
    private const DEFAULT_WAIT_FOR_CERTIFICATE = true;

    /**
     * Checks whether automatic suspension is enabled for the course.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_course_enabled(int $courseid): bool {
        return self::get_course_settings($courseid)['enabled'];
    }

    /**
     * Returns whether the course waits for certificate issuance.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_waits_for_certificate(int $courseid): bool {
        return self::get_course_settings($courseid)['waitforcertificate'];
    }

    /**
     * Returns effective per-course settings.
     *
     * @param int $courseid
     * @return array{enabled: bool, waitforcertificate: bool}
     */
    public static function get_course_settings(int $courseid): array {
        global $DB;

        $record = $DB->get_record('local_suspend_course', [
            'courseid' => $courseid,
        ], 'enabled, waitforcertificate', IGNORE_MISSING);
        if (!$record) {
            return [
                'enabled' => self::DEFAULT_ENABLED,
                'waitforcertificate' => self::DEFAULT_WAIT_FOR_CERTIFICATE,
            ];
        }

        return [
            'enabled' => !empty($record->enabled),
            'waitforcertificate' => !empty($record->waitforcertificate),
        ];
    }

    /**
     * Stores per-course suspension settings.
     *
     * @param int $courseid
     * @param bool $enabled
     * @param bool $waitforcertificate
     * @return void
     */
    public static function set_course_settings(int $courseid, bool $enabled, bool $waitforcertificate): void {
        global $DB;

        $existing = $DB->get_record('local_suspend_course', [
            'courseid' => $courseid,
        ], '*', IGNORE_MISSING);

        if ($enabled === self::DEFAULT_ENABLED && $waitforcertificate === self::DEFAULT_WAIT_FOR_CERTIFICATE) {
            if ($existing) {
                $DB->delete_records('local_suspend_course', ['id' => $existing->id]);
            }
            return;
        }

        $now = time();
        if ($existing) {
            $existing->enabled = (int)$enabled;
            $existing->waitforcertificate = (int)$waitforcertificate;
            $existing->timemodified = $now;
            $DB->update_record('local_suspend_course', $existing);
            return;
        }

        $DB->insert_record('local_suspend_course', (object)[
            'courseid' => $courseid,
            'enabled' => (int)$enabled,
            'waitforcertificate' => (int)$waitforcertificate,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
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

}
