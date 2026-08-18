@enrol @enrol_flexaccess
Feature: FlexAccess enrolment scaffold
  Scenario: The plugin is available after installation
    Given I log in as "admin"
    When I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    Then I should see "FlexAccess enrolment"
