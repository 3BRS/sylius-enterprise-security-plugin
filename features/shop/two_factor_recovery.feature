@shop @two_factor_recovery @ui
Feature: Customer two-factor authentication recovery-code fallback
    In order to regain access if I lose my authenticator device
    As a customer with 2FA enabled
    I want to use a recovery code instead of a TOTP code

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "Password1!"

    @ui
    Scenario: Customer uses a valid recovery code to complete 2FA challenge
        Given the customer "john@example.com" has 2FA enabled with recovery codes
        When I sign in with email "john@example.com" and password "Password1!"
        And I visit the recovery code challenge page
        And I submit a valid recovery code
        Then I should be fully authenticated

    @ui
    Scenario: Customer submits an invalid recovery code and stays on the recovery page
        Given the customer "john@example.com" has 2FA enabled with recovery codes
        When I sign in with email "john@example.com" and password "Password1!"
        And I visit the recovery code challenge page
        And I submit an invalid recovery code
        Then I should see a recovery code error

    @ui
    Scenario: A used recovery code is marked consumed
        Given the customer "john@example.com" has 2FA enabled with recovery codes
        When I sign in with email "john@example.com" and password "Password1!"
        And I visit the recovery code challenge page
        And I submit a valid recovery code
        Then the used recovery code should be marked consumed
