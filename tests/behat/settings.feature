@enrol @enrol_flexaccess
Feature: FlexAccess enrolment plugin availability
  Scenario: The FlexAccess enrolment method is available after installation
    Given I log in as "admin"
    When I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    Then I should see "FlexAccess enrolment"
