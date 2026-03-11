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
 * Tests for the course completion observer.
 *
 * @package    local_suspend
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_suspend\observer
 */
final class observer_test extends \advanced_testcase {
    public function test_course_completion_without_certificate_activity_suspends_student_enrolment(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();

        $studentroleid = $DB->get_field('role', 'id', ['archetype' => 'student'], MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $studentroleid, 'manual');
        $instance = $this->get_manual_enrol_instance($course->id);

        $completion = $this->create_course_completion_record($course->id, $user->id);
        $this->trigger_course_completed_event($completion);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);

        $this->assertSame(ENROL_USER_SUSPENDED, (int)$ue->status);
    }

    public function test_course_completion_and_customcert_issue_suspend_enrolment_for_inherited_student_role(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id, 'enablecompletion' => 1]);
        $user = $generator->create_user();

        $studentroleid = $DB->get_field('role', 'id', ['archetype' => 'student'], MUST_EXIST);
        role_assign($studentroleid, $user->id, \context_coursecat::instance($category->id)->id);

        $generator->enrol_user($user->id, $course->id, null, 'manual');
        $instance = $this->get_manual_enrol_instance($course->id);
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        $completion = $this->create_course_completion_record($course->id, $user->id);
        $this->trigger_course_completed_event($completion);
        \mod_customcert\certificate::issue_certificate($customcert->id, $user->id);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);

        $this->assertSame(ENROL_USER_SUSPENDED, (int)$ue->status);
    }

    public function test_course_completion_does_not_suspend_non_student_enrolment(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();

        $teacherroleid = $DB->get_field('role', 'id', ['archetype' => 'editingteacher'], MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $teacherroleid, 'manual');
        $instance = $this->get_manual_enrol_instance($course->id);

        $completion = $this->create_course_completion_record($course->id, $user->id);
        $this->trigger_course_completed_event($completion);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);

        $this->assertSame(ENROL_USER_ACTIVE, (int)$ue->status);
    }

    public function test_course_completion_with_certificate_activity_keeps_student_enrolment_active_until_issue(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();

        $studentroleid = $DB->get_field('role', 'id', ['archetype' => 'student'], MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $studentroleid, 'manual');
        $instance = $this->get_manual_enrol_instance($course->id);

        $generator->create_module('customcert', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        $completion = $this->create_course_completion_record($course->id, $user->id);
        $this->trigger_course_completed_event($completion);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);

        $this->assertSame(ENROL_USER_ACTIVE, (int)$ue->status);
    }

    public function test_course_completion_with_precomputed_certificate_cache_keeps_student_enrolment_active_until_issue(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();

        $studentroleid = $DB->get_field('role', 'id', ['archetype' => 'student'], MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $studentroleid, 'manual');
        $instance = $this->get_manual_enrol_instance($course->id);

        $generator->create_module('customcert', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        \local_suspend\manager::refresh_course_certificate_activity_cache([$course->id]);

        $cache = $DB->get_record('local_suspend_course_cache', [
            'courseid' => $course->id,
        ], '*', MUST_EXIST);
        $this->assertSame(1, (int)$cache->hascertificateactivity);

        $completion = $this->create_course_completion_record($course->id, $user->id);
        $this->trigger_course_completed_event($completion);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);

        $this->assertSame(ENROL_USER_ACTIVE, (int)$ue->status);
    }

    public function test_course_completion_waits_for_customcert_issue_before_suspending(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();

        $studentroleid = $DB->get_field('role', 'id', ['archetype' => 'student'], MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $studentroleid, 'manual');
        $instance = $this->get_manual_enrol_instance($course->id);

        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        $completionrecord = $this->create_course_completion_record($course->id, $user->id);
        $this->trigger_course_completed_event($completionrecord);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);
        $this->assertSame(ENROL_USER_ACTIVE, (int)$ue->status);

        \mod_customcert\certificate::issue_certificate($customcert->id, $user->id);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);
        $this->assertSame(ENROL_USER_SUSPENDED, (int)$ue->status);
    }

    public function test_completed_customcert_without_issue_keeps_enrolment_active(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();

        $studentroleid = $DB->get_field('role', 'id', ['archetype' => 'student'], MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $studentroleid, 'manual');
        $instance = $this->get_manual_enrol_instance($course->id);

        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        $completionrecord = $this->create_course_completion_record($course->id, $user->id);
        $this->trigger_course_completed_event($completionrecord);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);
        $this->assertSame(ENROL_USER_ACTIVE, (int)$ue->status);
    }

    public function test_customcert_issue_before_course_completion_suspends_on_completion(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();

        $studentroleid = $DB->get_field('role', 'id', ['archetype' => 'student'], MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $studentroleid, 'manual');
        $instance = $this->get_manual_enrol_instance($course->id);

        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        \mod_customcert\certificate::issue_certificate($customcert->id, $user->id);

        $completion = $this->create_course_completion_record($course->id, $user->id);
        $this->trigger_course_completed_event($completion);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);

        $this->assertSame(ENROL_USER_SUSPENDED, (int)$ue->status);
    }

    public function test_excluded_course_is_not_suspended_even_after_customcert_issue(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();

        set_config('excludedcourses', (string)$course->id, 'local_suspend');

        $studentroleid = $DB->get_field('role', 'id', ['archetype' => 'student'], MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $studentroleid, 'manual');
        $instance = $this->get_manual_enrol_instance($course->id);

        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        $completionrecord = $this->create_course_completion_record($course->id, $user->id);
        $this->trigger_course_completed_event($completionrecord);

        \mod_customcert\certificate::issue_certificate($customcert->id, $user->id);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);

        $this->assertSame(ENROL_USER_ACTIVE, (int)$ue->status);
    }

    private function get_manual_enrol_instance(int $courseid): \stdClass {
        global $DB;

        return $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'manual',
        ], '*', MUST_EXIST);
    }

    private function create_course_completion_record(int $courseid, int $userid): \stdClass {
        global $DB;

        $record = (object)[
            'course' => $courseid,
            'userid' => $userid,
            'timeenrolled' => time(),
            'timestarted' => time(),
            'timecompleted' => time(),
            'reaggregate' => 0,
        ];
        $record->id = $DB->insert_record('course_completions', $record);

        return $record;
    }

    private function trigger_course_completed_event(\stdClass $completion): void {
        \core\event\course_completed::create_from_completion($completion)->trigger();
    }
}
