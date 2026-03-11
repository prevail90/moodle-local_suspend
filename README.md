# local_suspend

Automatically suspends a student's enrolment in a course when the course is completed, with certificate-aware handling for courses that use supported certificate activities.

## Behaviour

- Observes the Moodle course completion event and certificate issuance flows.
- Maintains a scheduled per-course cache of whether supported certificate activities exist, so repeated student completions do not keep rechecking the same course structure.
- If a course does not contain a supported certificate activity, suspension happens on course completion alone.
- If a course contains `customcert` or `certificate`, suspension waits until both course completion and certificate issuance have been seen for the same user and course.
- `customcert` is handled from its dedicated `\\mod_customcert\\event\\issue_created` event.
- `certificate` is handled from its module view flow, with a deferred check for the issue row because that plugin does not emit a dedicated issue-created event.
- Confirms the user holds a student-archetype role for that course, including inherited role assignments from parent contexts.
- Suspends all active enrolments for that user in that course.

## Install

1. Place this folder as `local/suspend` in your Moodle codebase.
2. Visit Site administration to complete plugin installation.

## Settings

- Go to Site administration > Plugins > Local plugins > Manage excluded courses.
- Use the course autocomplete to choose excluded courses instead of entering raw IDs.
- The management page shows a small overview of total and excluded courses.

## Scheduled task

- The plugin includes a scheduled task that refreshes a per-course cache for `customcert` and `certificate` activities every 15 minutes.
- Course completion handling reads that cache first, which avoids repeating the same module lookup for every student finishing the same course.

## Admin workflow

1. Open `Manage excluded courses`.
2. Review the overview table.
3. Add or remove excluded courses using the autocomplete field.
4. Save changes.

## Notes

- This does not delete enrolments; it changes them to suspended.
- If a user has multiple active enrolment methods in the same course, each is suspended.
- The role check is based on the role archetype rather than a hard-coded shortname, so customized student role shortnames still work.
- Excluded courses are skipped for both course completion and certificate issuance handling.

## Releases

- The release workflow watches `version.php` on `main` and `master`, creates a tag from `$plugin->release`, and publishes a GitHub release automatically.
- Release tagging is based on `$plugin->release`, for example `0.5.0` becomes tag `v0.5.0`.
- Non-production releases must include a prerelease suffix in `$plugin->release`, for example `0.5.0-alpha`, `0.5.0-beta.3`, or `0.5.0-rc.1`.
- Stable releases must use a plain version number such as `0.5.0`.
- Release notes are generated from the commit subjects since the previous tag, so the release body reflects what changed or was fixed.
- Whether the GitHub release is published as stable or prerelease is based on `$plugin->maturity`.
- `MATURITY_STABLE` produces a normal release.
- `MATURITY_ALPHA`, `MATURITY_BETA`, and `MATURITY_RC` produce GitHub prereleases.
