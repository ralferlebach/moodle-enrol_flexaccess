@enrol @enrol_flexaccess @javascript
Feature: Configure FlexAccess enrolment access window and capacity
  In order to run time-boxed, capacity-limited access
  As a teacher or administrator
  I need to set an access window and a maximum participant count on the enrolment method

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "FlexAccess enrolment" "table_row"

  Scenario: Add a FlexAccess method with a maximum capacity
    Given I am on the "Course 1" "enrolment methods" page
    When I select "FlexAccess enrolment" from the "Add method" singleselect
    And I set the field "Maximum participants" to "30"
    And I press "Add method"
    Then I should see "FlexAccess enrolment"

  Scenario: Negative capacity is rejected
    Given I am on the "Course 1" "enrolment methods" page
    When I select "FlexAccess enrolment" from the "Add method" singleselect
    And I set the field "Maximum participants" to "-5"
    And I press "Add method"
    Then I should see "The maximum number of participants must be 0 (unlimited) or a positive number."
