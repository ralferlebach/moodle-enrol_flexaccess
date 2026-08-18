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
