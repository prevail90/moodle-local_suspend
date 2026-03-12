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
 * Event observers for local_suspend.
 *
 * @package    local_suspend
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\course_completed',
        'callback' => '\\local_suspend\\observer::course_completed',
        'includefile' => '/local/suspend/classes/observer.php',
        'priority' => 9999,
    ],
    [
        'eventname' => '\\mod_customcert\\event\\issue_created',
        'callback' => '\\local_suspend\\observer::customcert_issue_created',
        'includefile' => '/local/suspend/classes/observer.php',
        'priority' => 9999,
    ],
    [
        'eventname' => '\\tool_certificate\\event\\certificate_issued',
        'callback' => '\\local_suspend\\observer::tool_certificate_issue_created',
        'includefile' => '/local/suspend/classes/observer.php',
        'priority' => 9999,
    ],
];
