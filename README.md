# local_suspend

Automatically suspends a student's enrolment in a course when the student completes that course.

## Behaviour

- Observes Moodle events `\\core\\event\\course_completed` and `\\core\\event\\course_module_completion_updated`.
- Waits for any certificate activity in the course (`customcert` or `certificate`) to be completed before suspension.
- If the course completes first, suspension is deferred until the certificate activity is later completed.
- If the course has no certificate activity, suspension happens on course completion only.
- Confirms the user holds a student-archetype role for that course, including inherited role assignments from parent contexts.
- Suspends all active enrolments for that user in that course.

## Install

1. Place this folder as `local/suspend` in your Moodle codebase.
2. Visit Site administration to complete plugin installation.

## Settings

- Go to Site administration > Plugins > Local plugins > Suspend completed students.
- Open `Manage excluded courses` to choose excluded courses with autocomplete instead of entering raw IDs.
- The management page also shows a small overview of how many courses currently use certificate completion, course completion only, and how many are excluded.

## Notes

- This does not delete enrolments; it changes them to suspended.
- If a user has multiple active enrolment methods in the same course, each is suspended.
- The role check is based on the role archetype rather than a hard-coded shortname, so customized student role shortnames still work.
- If the course has no `customcert` or `certificate` activities, the plugin behaves like before and suspends on course completion.
- Certificate activities should have Moodle activity completion enabled, otherwise there is no completion signal for the prerequisite.
- Excluded courses are skipped for both course completion and certificate completion events.
