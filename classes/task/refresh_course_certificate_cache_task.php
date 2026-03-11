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

namespace local_suspend\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Refreshes the per-course certificate activity cache used by completion handling.
 *
 * @package    local_suspend
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_course_certificate_cache_task extends \core\task\scheduled_task {
    /**
     * Returns the task display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:refreshcoursecertificatecache', 'local_suspend');
    }

    /**
     * Executes the scheduled cache refresh.
     *
     * @return void
     */
    public function execute(): void {
        \local_suspend\manager::refresh_course_certificate_activity_cache();
    }
}
