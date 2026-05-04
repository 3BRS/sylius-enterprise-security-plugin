@admin @security_settings @ui
Feature: Admin can configure security settings via UI
    In order to manage authentication policies without redeploying the application
    As an administrator
    I want to change security settings from the admin panel and have them apply immediately

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password1!"

    Scenario: Admin opens the security settings page
        When I am logged in as "admin@example.com" administrator
        And I open the security settings page
        Then I should see the security settings configuration page
        And I should see the "Password policy" section
        And I should see the "Account lockout" section

    Scenario: Admin tightens customer password policy via UI
        When I am logged in as "admin@example.com" administrator
        And I open the security settings page
        And I switch to the "customer" scope
        And I change the customer minimum password length to 16
        Then the customer password minimum length should be 16

    Scenario: Admin enables passkey for customers via UI
        When I am logged in as "admin@example.com" administrator
        And I open the security settings page
        And I switch to the "customer" scope
        And I enable customer passkey
        Then the customer passkey feature should be enabled
