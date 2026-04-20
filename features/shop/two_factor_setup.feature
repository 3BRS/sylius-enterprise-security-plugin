@shop @two_factor @ui
Feature: Customer two-factor authentication setup
    In order to protect my account
    As a customer
    I want to enable TOTP-based two-factor authentication

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "Password1!"

    @ui
    Scenario: Customer sees the 2FA setup page with QR code and secret
        Given I am logged in as "john@example.com"
        When I visit the 2FA setup page
        Then I should see the 2FA QR code
        And I should see the TOTP secret

    @ui
    Scenario: Customer enables 2FA with a valid TOTP code
        Given I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I submit a valid TOTP code
        Then I should be on the 2FA recovery codes page
        And I should see 8 recovery codes
        And 2FA should be enabled for "john@example.com"

    @ui
    Scenario: Invalid TOTP code keeps the customer on the setup page
        Given I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I submit an invalid TOTP code
        Then I should remain on the 2FA setup page
        And 2FA should not be enabled for "john@example.com"

    @ui
    Scenario: Customer with 2FA enabled sees the manage view instead of setup
        Given the customer "john@example.com" already has 2FA enabled
        And I am logged in as "john@example.com"
        When I visit the 2FA setup page
        Then I should see the 2FA disable button

    @ui
    Scenario: Customer disables 2FA
        Given the customer "john@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I press the 2FA disable button
        Then 2FA should not be enabled for "john@example.com"
        And the customer should have no recovery codes

    @ui
    Scenario: Customer regenerates recovery codes — previous ones stop working
        Given the customer "john@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I press the regenerate recovery codes button
        Then I should be on the 2FA recovery codes page
        And I should see 8 recovery codes
        And none of the previous recovery codes should work for "john@example.com"
