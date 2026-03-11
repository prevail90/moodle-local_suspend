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
 * Deferred processor for the legacy certificate module issue flow.
 *
 * @package    local_suspend
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_certificate_view_task extends \core\task\adhoc_task {
    /**
     * Executes the task.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $data = (object)$this->get_custom_data();
        $cmid = (int)($data->cmid ?? 0);
        $userid = (int)($data->userid ?? 0);

        if (!$cmid || !$userid) {
            return;
        }

        [, $cm] = get_course_and_cm_from_cmid($cmid);
        if ($cm->modname !== 'certificate' || \local_suspend\manager::is_course_excluded((int)$cm->course)) {
            return;
        }

        if (!$DB->record_exists('certificate_issues', [
            'certificateid' => $cm->instance,
            'userid' => $userid,
        ])) {
            return;
        }

        \local_suspend\manager::mark_certificate_issued((int)$cm->course, $userid);
        \local_suspend\observer::suspend_if_ready((int)$cm->course, $userid);
    }
}
