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
 * Upgrade steps for local_suspend.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_suspend_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026031102) {
        $table = new xmldb_table('local_suspend_state');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coursecompleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('certificateissued', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseuseruniq', XMLDB_INDEX_UNIQUE, ['courseid', 'userid']);
        $table->add_index('useridix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026031102, 'local', 'suspend');
    }

    if ($oldversion < 2026031103) {
        $table = new xmldb_table('local_suspend_course_cache');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('hascertificateactivity', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseiduniq', XMLDB_INDEX_UNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026031103, 'local', 'suspend');
    }

    if ($oldversion < 2026040400) {
        $table = new xmldb_table('local_suspend_course_cache');

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2026040400, 'local', 'suspend');
    }

    if ($oldversion < 2026040401) {
        $table = new xmldb_table('local_suspend_course');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('waitforcertificate', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseiduniq', XMLDB_INDEX_UNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $configured = (string)get_config('local_suspend', 'excludedcourses');
        if ($configured !== '') {
            $courseids = preg_split('/[\s,]+/', trim($configured), -1, PREG_SPLIT_NO_EMPTY);
            $courseids = array_map('intval', $courseids ?: []);
            $courseids = array_filter($courseids, static fn(int $courseid): bool => $courseid > 0);
            $courseids = array_values(array_unique($courseids));
            $now = time();

            foreach ($courseids as $courseid) {
                if ($DB->record_exists('local_suspend_course', ['courseid' => $courseid])) {
                    continue;
                }

                $DB->insert_record('local_suspend_course', (object)[
                    'courseid' => $courseid,
                    'enabled' => 0,
                    'waitforcertificate' => 1,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }

            unset_config('excludedcourses', 'local_suspend');
        }

        upgrade_plugin_savepoint(true, 2026040401, 'local', 'suspend');
    }

    if ($oldversion < 2026040402) {
        $DB->delete_records('task_scheduled', [
            'classname' => '\local_suspend\task\refresh_course_certificate_cache_task',
        ]);
        unset_config('excludedcourses', 'local_suspend');

        upgrade_plugin_savepoint(true, 2026040402, 'local', 'suspend');
    }

    return true;
}
