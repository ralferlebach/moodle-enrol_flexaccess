@enrol @enrol_flexaccess @javascript
Feature: Protect temporary-user entry with a shared access key
  In order to restrict temporary access to invited participants
  As a teacher or administrator
  I need a system or course access key to gate only temporary-user FlexAccess modes

  Scenario: Course access key does not replace normal Moodle login
    Given the FlexAccess enrolment method is configured with a course access key
    When a visitor opens the FlexAccess entry page for the course
    Then temporary-user entry requires the shared access key
    And normal Moodle login remains available without that shared access key
