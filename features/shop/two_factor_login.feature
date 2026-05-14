@shop @two_factor_login @ui
Feature: Customer two-factor authentication login flow
    In order to protect my account during login
    As a customer with 2FA enabled
    I want to be prompted for a TOTP code after entering my password

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "Password1!"

    @ui
    Scenario: Customer with 2FA enabled is redirected to the challenge page after login
        Given the customer "john@example.com" has 2FA enabled with a known secret
        When I sign in with email "john@example.com" and password "Password1!"
        Then I should be on the 2FA challenge page

    @ui
    Scenario: Customer completes 2FA challenge with a valid TOTP code
        Given the customer "john@example.com" has 2FA enabled with a known secret
        When I sign in with email "john@example.com" and password "Password1!"
        And I submit a valid TOTP challenge code
        Then I should be fully authenticated

    @ui
    Scenario: Customer submits an invalid code and stays on the challenge page
        Given the customer "john@example.com" has 2FA enabled with a known secret
        When I sign in with email "john@example.com" and password "Password1!"
        And I submit an invalid TOTP challenge code
        Then I should be on the 2FA challenge page
        And I should see a 2FA authentication error
