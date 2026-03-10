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
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_suspend_manage');
require_login();
require_capability('moodle/site:config', context_system::instance());

$url = new moodle_url('/local/suspend/manage.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('manageexclusions', 'local_suspend'));
$PAGE->set_heading(get_string('manageexclusions', 'local_suspend'));
$PAGE->set_secondary_navigation(false);

$courseoptions = \local_suspend\manager::get_course_options();
$form = new \local_suspend\form\exclusions_form(null, ['courseoptions' => $courseoptions]);
$form->set_data([
    'excludedcourses' => \local_suspend\manager::get_excluded_course_ids(),
]);

if ($data = $form->get_data()) {
    $excludedcourses = $data->excludedcourses ?? [];
    if (!is_array($excludedcourses)) {
        $excludedcourses = [$excludedcourses];
    }

    \local_suspend\manager::set_excluded_course_ids($excludedcourses);
    redirect($url, get_string('changessaved'));
}

$summary = \local_suspend\manager::get_course_summary();
$excludedcourses = \local_suspend\manager::get_excluded_courses();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_suspend'));
echo html_writer::tag('p', get_string('manageexclusions_desc', 'local_suspend'));

$summarytable = new html_table();
$summarytable->head = [
    get_string('totalcourses', 'local_suspend'),
    get_string('certificatecourses', 'local_suspend'),
    get_string('coursecompletiononlycourses', 'local_suspend'),
    get_string('excludedcoursescount', 'local_suspend'),
];
$summarytable->data[] = [
    $summary['totalcourses'],
    $summary['certificatecourses'],
    $summary['coursecompletiononly'],
    $summary['excludedcourses'],
];

echo $OUTPUT->heading(get_string('overview', 'local_suspend'), 3);
echo html_writer::table($summarytable);

echo $OUTPUT->heading(get_string('manageexclusions', 'local_suspend'), 3);
$form->display();

echo $OUTPUT->heading(get_string('excludedcourselist', 'local_suspend'), 3);
if (!$excludedcourses) {
    echo $OUTPUT->notification(get_string('noexcludedcourses', 'local_suspend'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('courseid', 'local_suspend'),
        get_string('fullname'),
        get_string('courseshortname', 'local_suspend'),
        get_string('processingmode', 'local_suspend'),
    ];

    foreach ($excludedcourses as $course) {
        $mode = \local_suspend\manager::course_has_certificate_requirement((int)$course->id)
            ? get_string('processingmode_certificate', 'local_suspend')
            : get_string('processingmode_completion', 'local_suspend');
        $table->data[] = [
            $course->id,
            format_string($course->fullname),
            s($course->shortname),
            $mode,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
