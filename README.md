# local_suspend

Automatically suspends a student's enrolment in a course when the student completes that course.

## Behaviour

- Observes Moodle events `\\core\\event\\course_completed` and `\\core\\event\\course_module_completion_updated`.
- If the course contains a supported certificate activity (`customcert` or `certificate`), suspension waits for that activity to be completed.
- If the course completes first, suspension is deferred until the certificate activity is later completed.
- If the course has no certificate activity, suspension happens on course completion only.
- Confirms the user holds a student-archetype role for that course, including inherited role assignments from parent contexts.
- Suspends all active enrolments for that user in that course.

## Install

1. Place this folder as `local/suspend` in your Moodle codebase.
2. Visit Site administration to complete plugin installation.

## Settings

- Go to Site administration > Plugins > Local plugins > Manage excluded courses.
- Use the course autocomplete to choose excluded courses instead of entering raw IDs.
- The management page also shows a small overview of how many courses currently use certificate completion, course completion only, and how many are excluded.

## Admin workflow

1. Open `Manage excluded courses`.
2. Review the overview table.
3. Add or remove excluded courses using the autocomplete field.
4. Save changes.

The excluded course list shows how each excluded course would otherwise be processed:

- `Certificate completion required`
- `Course completion only`

## Notes

- This does not delete enrolments; it changes them to suspended.
- If a user has multiple active enrolment methods in the same course, each is suspended.
- The role check is based on the role archetype rather than a hard-coded shortname, so customized student role shortnames still work.
- Certificate activities should have Moodle activity completion enabled, otherwise there is no completion signal for the prerequisite.
- Excluded courses are skipped for both course completion and certificate completion events.

## Releases

- The release workflow watches `version.php` on `main` and `master`, creates a tag from `$plugin->release`, and publishes a GitHub release automatically.
- Release tagging is based on `$plugin->release`, for example `0.5.0` becomes tag `v0.5.0`.
- Non-production releases must include a prerelease suffix in `$plugin->release`, for example `0.5.0-alpha`, `0.5.0-beta.3`, or `0.5.0-rc.1`.
- Stable releases must use a plain version number such as `0.5.0`.
- Release notes are generated from the commit subjects since the previous tag, so the release body reflects what changed or was fixed.
- Whether the GitHub release is published as stable or prerelease is based on `$plugin->maturity`.
- `MATURITY_STABLE` produces a normal release.
- `MATURITY_ALPHA`, `MATURITY_BETA`, and `MATURITY_RC` produce GitHub prereleases.
