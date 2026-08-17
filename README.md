# enrol_flexaccess

Moodle enrolment plugin scaffold for FlexAccess course/category policy, automatic course enrolment, enrolment expiry and temporary-user participant-list policy.

Depends on `auth_flexaccess` 0.1.0-alpha. **Configuration fields are scaffolding; production enrolment flows are not implemented yet.**

A system-wide or course-specific shared access key can optionally gate temporary-user entry. System and course keys are stored hash-only; category policies do not define shared keys.
