@admin @two_factor_recovery @ui
Feature: Administrator two-factor authentication recovery-code fallback
    In order to regain access if I lose my authenticator device
    As an administrator with 2FA enabled
    I want to use a recovery code instead of a TOTP code

    Background:
        Given there is an administrator "admin@example.com" identified by "Sylius1!"

    @ui
    Scenario: Administrator uses a valid recovery code to complete 2FA challenge
        Given the administrator "admin@example.com" has 2FA enabled with recovery codes
        And I want to log in
        When I specify the username as "admin@example.com"
        And I specify the password as "Sylius1!"
        And I log in
        And I visit the admin recovery code challenge page
        And I submit a valid admin recovery code
        Then I should be fully authenticated as administrator

    @ui
    Scenario: Administrator submits an invalid recovery code and stays on the recovery page
        Given the administrator "admin@example.com" has 2FA enabled with recovery codes
        And I want to log in
        When I specify the username as "admin@example.com"
        And I specify the password as "Sylius1!"
        And I log in
        And I visit the admin recovery code challenge page
        And I submit an invalid admin recovery code
        Then I should see an admin recovery code error

    @ui
    Scenario: A used admin recovery code is marked consumed
        Given the administrator "admin@example.com" has 2FA enabled with recovery codes
        And I want to log in
        When I specify the username as "admin@example.com"
        And I specify the password as "Sylius1!"
        And I log in
        And I visit the admin recovery code challenge page
        And I submit a valid admin recovery code
        Then the used admin recovery code should be marked consumed
