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

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($course->id);

require_login($course);
require_capability('moodle/course:update', $context);

$url = new moodle_url('/local/suspend/course.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('coursesettings', 'local_suspend'));
$PAGE->set_heading(format_string($course->fullname));

$form = new \local_suspend\form\course_settings_form($url);
$settings = \local_suspend\manager::get_course_settings($course->id);
$form->set_data([
    'enabled' => (int)$settings['enabled'],
    'waitforcertificate' => (int)$settings['waitforcertificate'],
]);

if ($data = $form->get_data()) {
    $waitforcertificate = property_exists($data, 'waitforcertificate')
        ? !empty($data->waitforcertificate)
        : $settings['waitforcertificate'];

    \local_suspend\manager::set_course_settings(
        $course->id,
        !empty($data->enabled),
        $waitforcertificate
    );

    redirect($url, get_string('changessaved'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursesettings', 'local_suspend'));
echo html_writer::tag('p', get_string('coursesettings_desc', 'local_suspend'));
$form->display();
echo $OUTPUT->footer();
