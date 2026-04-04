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
 * Tests for manager helpers.
 *
 * @package    local_suspend
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_suspend\manager
 */
final class manager_test extends \advanced_testcase {
    public function test_completion_and_certificate_signals_make_user_ready_to_suspend(): void {
        $this->resetAfterTest();

        manager::mark_course_completed(42, 7);
        $this->assertFalse(manager::is_ready_to_suspend(42, 7));

        manager::mark_certificate_issued(42, 7);
        $this->assertTrue(manager::is_ready_to_suspend(42, 7));
    }

    public function test_clear_suspend_state_removes_existing_signals(): void {
        global $DB;

        $this->resetAfterTest();

        manager::mark_course_completed(42, 7);
        manager::mark_certificate_issued(42, 7);

        $this->assertTrue($DB->record_exists('local_suspend_state', [
            'courseid' => 42,
            'userid' => 7,
        ]));

        manager::clear_suspend_state(42, 7);

        $this->assertFalse($DB->record_exists('local_suspend_state', [
            'courseid' => 42,
            'userid' => 7,
        ]));
    }

    public function test_disabled_course_settings_opt_the_course_out(): void {
        $this->resetAfterTest();

        manager::set_course_settings(42, false, true);

        $this->assertFalse(manager::is_course_enabled(42));
        $this->assertTrue(manager::course_waits_for_certificate(42));
    }

    public function test_course_settings_default_to_enabled_and_waiting_for_certificate(): void {
        $this->resetAfterTest();

        $this->assertSame([
            'enabled' => true,
            'waitforcertificate' => true,
        ], manager::get_course_settings(42));
    }

    public function test_set_course_settings_persists_non_default_values(): void {
        $this->resetAfterTest();

        manager::set_course_settings(42, false, false);

        $this->assertSame([
            'enabled' => false,
            'waitforcertificate' => false,
        ], manager::get_course_settings(42));
    }
}
