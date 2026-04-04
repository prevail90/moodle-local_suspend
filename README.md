# local_suspend

Automatically suspends a student's enrolment in a course after course completion, with optional waiting for certificate issuance on a per-course basis.

## Behaviour

- Observes the Moodle course completion event and certificate issuance flows.
- Each course can be opted out individually from its own suspension settings page.
- By default, suspension waits until both course completion and certificate issuance have been seen for the same user and course.
- A course can be configured to suspend immediately on course completion instead of waiting for the certificate event.
- `customcert` is handled from its dedicated `\\mod_customcert\\event\\issue_created` event.
- `coursecertificate` is handled from the Certificate manager event `\\tool_certificate\\event\\certificate_issued`, filtered to issues created for `mod_coursecertificate`.
- Confirms the user holds a student-archetype role for that course, including inherited role assignments from parent contexts.
- Suspends all active enrolments for that user in that course.

## Install

1. Place this folder as `local/suspend` in your Moodle codebase.
2. Visit Site administration to complete plugin installation.

## Settings

- Go to a course and open `Course administration > Suspension settings` to control behavior for that course.
- Use the course page to opt a course out completely or to disable the certificate wait requirement.

## Course workflow

1. Open a course and choose `Suspension settings`.
2. Decide whether the course is enabled and whether it should wait for certificate issuance.
3. Save changes.

## Notes

- This does not delete enrolments; it changes them to suspended.
- If a user has multiple active enrolment methods in the same course, each is suspended.
- The role check is based on the role archetype rather than a hard-coded shortname, so customized student role shortnames still work.
- If a course is configured to wait for a certificate and no certificate issue is observed, the enrolment stays active.
- Disabled courses are skipped for both course completion and certificate issuance handling.

## Releases

- The release workflow watches `version.php` on `main` and `master`, creates a tag from `$plugin->release`, and publishes a GitHub release automatically.
- Release tagging is based on `$plugin->release`, for example `0.5.0` becomes tag `v0.5.0`.
- Non-production releases must include a prerelease suffix in `$plugin->release`, for example `0.5.0-alpha`, `0.5.0-beta.3`, or `0.5.0-rc.1`.
- Stable releases must use a plain version number such as `0.5.0`.
- Release notes are generated from the commit subjects since the previous tag, so the release body reflects what changed or was fixed.
- Whether the GitHub release is published as stable or prerelease is based on `$plugin->maturity`.
- `MATURITY_STABLE` produces a normal release.
- `MATURITY_ALPHA`, `MATURITY_BETA`, and `MATURITY_RC` produce GitHub prereleases.
