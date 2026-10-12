@admin @two_factor @ui
Feature: Administrator two-factor authentication setup
    In order to protect my administrator account
    As an administrator
    I want to enable TOTP-based two-factor authentication

    Background:
        Given there is an administrator "admin@example.com" identified by "Sylius1!"

    @ui
    Scenario: Administrator sees the 2FA setup page with QR code and secret
        Given I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        Then I should see the admin 2FA QR code
        And I should see the admin TOTP secret

    @ui
    Scenario: Administrator enables 2FA with a valid TOTP code
        Given I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        And I submit a valid admin TOTP code
        Then I should be on the admin 2FA recovery codes page
        And I should see 8 admin recovery codes
        And admin 2FA should be enabled for "admin@example.com"

    @ui
    Scenario: Invalid TOTP code keeps the administrator on the setup page
        Given I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        And I submit an invalid admin TOTP code
        Then I should remain on the admin 2FA setup page
        And admin 2FA should not be enabled for "admin@example.com"

    @ui
    Scenario: Administrator with 2FA enabled sees the manage view instead of setup
        Given the administrator "admin@example.com" already has 2FA enabled
        And I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        Then I should see the admin 2FA disable button

    @ui
    Scenario: Administrator disables 2FA
        Given the administrator "admin@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        And I disable admin 2FA confirming with the current authenticator code
        Then admin 2FA should not be enabled for "admin@example.com"
        And the administrator should have no recovery codes

    @ui
    Scenario: Administrator regenerates recovery codes — previous ones stop working
        Given the administrator "admin@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        And I regenerate admin recovery codes confirming with the current authenticator code
        Then I should be on the admin 2FA recovery codes page
        And I should see 8 admin recovery codes
        And none of the previous admin recovery codes should work for "admin@example.com"

    @ui
    Scenario: Administrator disables 2FA with the authenticator code written with a dash
        Given the administrator "admin@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        And I disable admin 2FA confirming with the current authenticator code written with a dash
        Then admin 2FA should not be enabled for "admin@example.com"

    @ui
    Scenario: Administrator disables 2FA with a recovery code
        Given the administrator "admin@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        And I disable admin 2FA confirming with the recovery code "AAAAA-11111"
        Then admin 2FA should not be enabled for "admin@example.com"
        And the administrator should have no recovery codes

    @ui @T85
    Scenario: A wrong code leaves administrator 2FA enabled
        Given the administrator "admin@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        And I try to disable admin 2FA with the code "000000"
        Then I should be told the admin two-factor code is invalid
        And I should see the admin 2FA disable button
        And admin 2FA should be enabled for "admin@example.com"

    @ui
    Scenario: A wrong code does not regenerate administrator recovery codes
        Given the administrator "admin@example.com" already has 2FA enabled with recovery codes
        And I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        And I try to regenerate admin recovery codes with the code "000000"
        Then I should be told the admin two-factor code is invalid
        And I should see the admin 2FA disable button

    @ui @T86
    Scenario: Administrator code attempts are limited to five per fifteen minutes
        Given the administrator "admin@example.com" already has 2FA enabled
        And I am logged in as "admin@example.com" administrator
        When I visit the admin 2FA setup page
        And I try to disable admin 2FA with the code "000000" 5 times
        And I disable admin 2FA confirming with the current authenticator code
        Then I should be told to try the admin two-factor code again later
        And I should see the admin 2FA disable button
        And admin 2FA should be enabled for "admin@example.com"
