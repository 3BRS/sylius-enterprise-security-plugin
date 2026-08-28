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

    @combination
    Scenario: A length past the bound is refused and the stored one survives
        # PASSWORD_POLICY_MIN_LENGTH_MAX is 64. The browser would stop 999 at the
        # input, which is exactly why the server is asked here instead: a plain
        # POST never sees the spinner.
        When I am logged in as "admin@example.com" administrator
        And I open the security settings page
        And I switch to the "customer" scope
        And I change the customer minimum password length to 16
        Then the customer password minimum length should be 16
        When I change the customer minimum password length to 999
        Then the customer password minimum length should be 16

    Scenario: Admin enables passkey for customers via UI
        Given customer passkey is switched off
        When I am logged in as "admin@example.com" administrator
        And I open the security settings page
        And I switch to the "customer" scope
        And I enable customer passkey
        Then the customer passkey feature should be enabled

    Scenario: Customer account lockout settings applied via UI take effect on next customer login
        Given there is a customer account "lockout-test@example.com" identified by "Password1!"
        When I am logged in as "admin@example.com" administrator
        And I open the security settings page
        And I switch to the "customer" scope
        And I enable customer account lockout with max attempts 2
        And customer "lockout-test@example.com" attempts 2 failed sign-ins
        Then customer "lockout-test@example.com" should be locked

    Scenario: Admin tightens customer login rate limit via UI
        When I am logged in as "admin@example.com" administrator
        And I open the security settings page
        And I switch to the "customer" scope
        And I tighten customer login rate limit to 1 per minute
        Then the customer login rate limit should be 1 per minute

    Scenario: Admin disables customer Google OAuth via UI
        When I am logged in as "admin@example.com" administrator
        And I open the security settings page
        And I switch to the "customer" scope
        And I disable customer Google OAuth
        Then the customer Google OAuth should be disabled
