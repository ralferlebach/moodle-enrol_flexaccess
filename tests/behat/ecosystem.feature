@enrol @enrol_flexaccess @flexaccess_ecosystem
Feature: FlexAccess plugins work together across the temporary-access lifecycle
  In order to trust the end-to-end flow
  As an administrator
  I need the enrol access flow, the auth account store and the tool dashboard to agree

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |

  Scenario: A temporary account created by the enrol flow is reported on the tool dashboard
    Given a FlexAccess temporary account is granted in course "Course 1"
    And I log in as "admin"
    When I visit "/admin/tool/flexaccess/index.php"
    Then I should see "Dashboard"
    And I should see "1" in the "Temporary users" "table_row"

  Scenario: An anonymous visitor gains temporary access through the entry page (deep link)
    Given a FlexAccess enrolment method allowing temporary access exists in course "Course 1"
    When I open the FlexAccess entry page for course "Course 1"
    And I press "Continue"
    Then I should see "Course 1"

  Scenario: A course access key gates anonymous temporary entry
    Given a FlexAccess enrolment method requiring access key "OPEN-SESAME" exists in course "Course 1"
    When I open the FlexAccess entry page for course "Course 1"
    And I set the field "Access key" to "wrong-key"
    And I press "Continue"
    Then I should see "That access key is not correct"
    And I set the field "Access key" to "OPEN-SESAME"
    And I press "Continue"
    Then I should see "Course 1"

  Scenario: Quick registration creates a persistent account that can log in again
    Given a FlexAccess enrolment method allowing quick registration exists in course "Course 1"
    When I open the FlexAccess quick registration page for course "Course 1"
    And I set the following fields to these values:
      | Email address | learner@example.com |
      | First name    | Test                |
      | Last name     | Learner             |
      | Password      | Str0ng-Pass!23      |
    And I press "Create account and enter"
    Then I should see "Course 1"
    And I log out
    And I open the site login page
    And I set the field "Username" to "learner@example.com"
    And I set the field "Password" to "Str0ng-Pass!23"
    And I press "Log in"
    Then I should see "Test Learner"

  Scenario: The entry page offers guest access and normal login as alternatives
    Given a FlexAccess enrolment method offering guest access and normal login exists in course "Course 1"
    When I open the FlexAccess entry page for course "Course 1"
    Then I should see "Continue as a guest"
    And I should see "Already have an account? Log in"
    And I follow "Continue as a guest"
    Then I should see "Course 1"
