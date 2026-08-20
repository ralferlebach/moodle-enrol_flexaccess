# enrol_flexaccess

Moodle enrolment plugin for FlexAccess course/category policy, automatic course enrolment, enrolment expiry and temporary-user participant-list-access policy.

Depends on `auth_flexaccess` (same build). Maturity: Beta (0.9.x); the enrolment, gating and quick-registration flows are implemented and tested.

A system-wide or course-specific shared access key can optionally gate temporary-user entry. System and course keys are stored hash-only; category policies do not define shared keys.
