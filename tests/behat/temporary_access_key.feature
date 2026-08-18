@enrol @enrol_flexaccess
Feature: FlexAccess temporary access can be gated by a shared access key
  In order to protect a course while keeping entry low-barrier
  As an administrator
  I need temporary access to require the correct shared key

  Background:
    Given the following "courses" exist:
      | fullname     | shortname | category |
      | Keyed course | KEYC      | 0        |

  Scenario: The correct shared key is required for temporary entry
    Given a FlexAccess enrolment method requiring access key "OPEN-SESAME" exists in course "Keyed course"
    When I open the FlexAccess entry page for course "Keyed course"
    Then I should see "Access key"
    And I set the field "Access key" to "wrong-key"
    And I press "Continue"
    Then I should see "That access key is not correct."
    And I set the field "Access key" to "OPEN-SESAME"
    And I press "Continue"
    Then I should see "Keyed course"
