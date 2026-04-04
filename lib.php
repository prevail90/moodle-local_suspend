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

defined('MOODLE_INTERNAL') || die();

/**
 * Adds local_suspend settings to the course administration navigation.
 *
 * @param navigation_node $parentnode
 * @param stdClass $course
 * @param context_course $context
 * @return void
 */
function local_suspend_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context): void {
    if ((int)$course->id === SITEID || !has_capability('moodle/course:update', $context)) {
        return;
    }

    $url = new moodle_url('/local/suspend/course.php', ['id' => $course->id]);
    $parentnode->add(
        get_string('coursesettings', 'local_suspend'),
        $url,
        navigation_node::TYPE_SETTING
    );
}
