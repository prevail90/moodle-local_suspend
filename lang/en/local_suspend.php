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

/**
 * Strings for component 'local_suspend'.
 *
 * @package    local_suspend
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Suspend completed students';
$string['coursesettings'] = 'Suspension settings';
$string['coursesettings_desc'] = 'Configure whether this course uses automatic suspension and whether suspension should wait for certificate issuance.';
$string['coursesettings:enabled'] = 'Enable automatic suspension for this course';
$string['coursesettings:enabled_help'] = 'If disabled, this course is opted out and the plugin will ignore both course completion and certificate issuance events for it.';
$string['coursesettings:waitforcertificate'] = 'Wait for certificate issuance before suspending completed students';
$string['coursesettings:waitforcertificate_help'] = 'If enabled, suspension happens only after both course completion and certificate issuance have been observed. If disabled, suspension happens as soon as course completion is observed.';
$string['privacy:metadata:local_suspend_state'] = 'The local_suspend plugin stores suspension workflow signals for each user and course.';
$string['privacy:metadata:local_suspend_state:courseid'] = 'The course where the suspension workflow is being tracked.';
$string['privacy:metadata:local_suspend_state:userid'] = 'The user whose suspension workflow is being tracked.';
$string['privacy:metadata:local_suspend_state:coursecompleted'] = 'Whether the course completion event has been observed.';
$string['privacy:metadata:local_suspend_state:certificateissued'] = 'Whether a supported certificate issuance event has been observed.';
