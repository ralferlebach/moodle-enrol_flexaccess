# Compatibility spike — temporary-user visibility in participant lists

Product requirement: a system default plus per-course `inherit/show/hide` setting.

Technical warning: core enrolment status is active/suspended; it is not a per-user visibility flag. The implementation must identify a supported public extension point that can filter temporary users from learner-facing participant lists while administrators/teachers retain management visibility.

Acceptance matrix: Moodle 4.5, 5.1, 5.2; Boost; MariaDB/PostgreSQL; no Core patch; PHPUnit/Behat regression coverage. Until this spike is solved, the `hide` setting is configuration scaffolding only and must not be presented as operational in a production release.
