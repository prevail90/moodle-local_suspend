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
 * Tests for course certificate activity caching.
 *
 * @package    local_suspend
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_suspend\manager
 * @covers \local_suspend\task\refresh_course_certificate_cache_task
 */
final class manager_test extends \advanced_testcase {
    public function test_refresh_course_certificate_activity_cache_marks_supported_courses(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $plaincourse = $generator->create_course();
        $certcourse = $generator->create_course();
        $generator->create_module('customcert', ['course' => $certcourse->id]);

        manager::refresh_course_certificate_activity_cache();

        $plaincache = $DB->get_record('local_suspend_course_cache', [
            'courseid' => $plaincourse->id,
        ], '*', MUST_EXIST);
        $certcache = $DB->get_record('local_suspend_course_cache', [
            'courseid' => $certcourse->id,
        ], '*', MUST_EXIST);

        $this->assertSame(0, (int)$plaincache->hascertificateactivity);
        $this->assertSame(1, (int)$certcache->hascertificateactivity);
    }

    public function test_refresh_course_certificate_activity_cache_removes_stale_rows(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $DB->insert_record('local_suspend_course_cache', (object)[
            'courseid' => 999999,
            'hascertificateactivity' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        manager::refresh_course_certificate_activity_cache([$course->id]);
        $this->assertTrue($DB->record_exists('local_suspend_course_cache', ['courseid' => 999999]));

        manager::refresh_course_certificate_activity_cache();
        $this->assertFalse($DB->record_exists('local_suspend_course_cache', ['courseid' => 999999]));
    }

    public function test_scheduled_task_refreshes_course_certificate_activity_cache(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->create_module('customcert', ['course' => $course->id]);

        $task = new \local_suspend\task\refresh_course_certificate_cache_task();
        $task->execute();

        $cache = $DB->get_record('local_suspend_course_cache', [
            'courseid' => $course->id,
        ], '*', MUST_EXIST);

        $this->assertSame(1, (int)$cache->hascertificateactivity);
    }
}
