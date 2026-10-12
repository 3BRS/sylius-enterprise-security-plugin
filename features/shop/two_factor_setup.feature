@shop @two_factor @ui
Feature: Customer two-factor authentication setup
    In order to protect my account
    As a customer
    I want to enable TOTP-based two-factor authentication

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "Password1!"

    @ui @T22
    Scenario: Customer sees the 2FA setup page with QR code and secret
        Given I am logged in as "john@example.com"
        When I visit the 2FA setup page
        Then I should see the 2FA QR code
        And I should see the TOTP secret

    @ui @T20
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

    @ui @T19
    Scenario: Customer disables 2FA
        Given the customer "john@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I disable 2FA confirming with the current authenticator code
        Then 2FA should not be enabled for "john@example.com"
        And the customer should have no recovery codes

    @ui @T25
    Scenario: Customer regenerates recovery codes — previous ones stop working
        Given the customer "john@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I regenerate recovery codes confirming with the current authenticator code
        Then I should be on the 2FA recovery codes page
        And I should see 8 recovery codes
        And none of the previous recovery codes should work for "john@example.com"

    @ui
    Scenario: Customer disables 2FA with the authenticator code written with a dash
        Given the customer "john@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I disable 2FA confirming with the current authenticator code written with a dash
        Then 2FA should not be enabled for "john@example.com"

    @ui
    Scenario: Customer disables 2FA with a recovery code
        Given the customer "john@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I disable 2FA confirming with the recovery code "AAAAA-11111"
        Then 2FA should not be enabled for "john@example.com"
        And the customer should have no recovery codes

    @ui @T85
    Scenario: A wrong code leaves 2FA enabled
        Given the customer "john@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I try to disable 2FA with the code "000000"
        Then I should be told the two-factor code is invalid
        And I should see the 2FA disable button
        And 2FA should be enabled for "john@example.com"

    @ui
    Scenario: A wrong code does not regenerate recovery codes
        Given the customer "john@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I try to regenerate recovery codes with the code "000000"
        Then I should be told the two-factor code is invalid
        And I should see the 2FA disable button

    @ui @T86
    Scenario: Code attempts are limited to five per fifteen minutes
        Given the customer "john@example.com" already has 2FA enabled
        And I am logged in as "john@example.com"
        When I visit the 2FA setup page
        And I try to disable 2FA with the code "000000" 5 times
        And I disable 2FA confirming with the current authenticator code
        Then I should be told to try again later
        And 2FA should be enabled for "john@example.com"
