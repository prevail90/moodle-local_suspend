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

namespace local_suspend\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for course-level suspension settings.
 *
 * @package    local_suspend
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_settings_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('advcheckbox', 'enabled',
            get_string('coursesettings:enabled', 'local_suspend')
        );
        $mform->addHelpButton('enabled', 'coursesettings:enabled', 'local_suspend');

        $mform->addElement('advcheckbox', 'waitforcertificate',
            get_string('coursesettings:waitforcertificate', 'local_suspend')
        );
        $mform->addHelpButton('waitforcertificate', 'coursesettings:waitforcertificate', 'local_suspend');
        $mform->disabledIf('waitforcertificate', 'enabled', 'notchecked');

        $this->add_action_buttons(false, get_string('savechanges'));
    }
}
