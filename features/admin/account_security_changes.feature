@admin @account_security_changes @ui
Feature: Account security changes need a full administrator sign-in
    In order to keep my second factor from being bypassed
    As an administrator
    I want linking a social account, adding a passkey and managing 2FA to need a full sign-in

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Sylius1!"

    @ui @T76
    Scenario: A sign-in waiting for its two-factor code cannot link a social account
        Given the administrator "admin@example.com" has 2FA enabled with a known secret
        And the "google" OAuth provider will return admin user "g-admin-pending-1" with email "admin@example.com"
        When I sign in to the admin with email "admin@example.com" and password "Sylius1!"
        And I start linking my admin "google" account
        Then I should be on the admin 2FA challenge page
        And the administrator "admin@example.com" should not be linked to "google"

    @ui
    Scenario: A sign-in waiting for its two-factor code can sign in through a linked provider instead
        Given the administrator "admin@example.com" has 2FA enabled with a known secret
        And the admin "admin@example.com" is already linked to "google" with id "g-admin-linked-1"
        And the "google" OAuth provider will return admin user "g-admin-linked-1" with email "admin@example.com"
        When I sign in to the admin with email "admin@example.com" and password "Sylius1!"
        And I click the admin "google" social login button
        Then I should be fully authenticated as administrator

    @ui
    Scenario: An administrator who entered the password and the code can link a social account
        Given the administrator "admin@example.com" has 2FA enabled with a known secret
        And the "google" OAuth provider will return admin user "g-admin-full-1" with email "admin@example.com"
        When I sign in to the admin with email "admin@example.com" and password "Sylius1!"
        And I submit a valid admin TOTP challenge code
        And I start linking my admin "google" account
        Then an admin social link should exist for "admin@example.com" with "google" and provider id "g-admin-full-1"

    @ui
    Scenario: An administrator signed in through a remember-me cookie cannot link a social account
        Given I am signed in to the admin through a remember-me cookie as "admin@example.com" with password "Sylius1!"
        And the "google" OAuth provider will return admin user "g-admin-remembered-1" with email "admin@example.com"
        When I start linking my admin "google" account
        Then I should be on the admin login page
        And the administrator "admin@example.com" should not be linked to "google"

    @ui
    Scenario: An administrator signed in through a remember-me cookie cannot add a passkey
        Given I am signed in to the admin through a remember-me cookie as "admin@example.com" with password "Sylius1!"
        When I request admin passkey registration options
        Then the admin passkey registration should be refused
        When I submit an admin passkey registration
        Then the admin passkey registration should be refused

    @ui
    Scenario: A fully signed-in administrator gets passkey registration options
        Given I am logged in as "admin@example.com" administrator
        When I request admin passkey registration options
        Then the admin passkey registration options should be issued

    @ui
    Scenario: An administrator signed in through a remember-me cookie signs in again before the 2FA setup and returns to it
        Given I am signed in to the admin through a remember-me cookie as "admin@example.com" with password "Sylius1!"
        When I visit the admin 2FA setup page
        Then I should be on the admin login page
        When I sign in to the admin with email "admin@example.com" and password "Sylius1!"
        Then I should see the admin 2FA QR code

    @ui
    Scenario: An administrator signed in through a remember-me cookie cannot disable 2FA
        Given I am signed in to the admin through a remember-me cookie as "admin@example.com" with password "Sylius1!"
        And the administrator "admin@example.com" already has 2FA enabled
        When I try to disable admin two-factor authentication
        Then I should be on the admin login page
        And admin 2FA should be enabled for "admin@example.com"

    @ui
    Scenario: An administrator signed in through a remember-me cookie cannot regenerate recovery codes
        Given I am signed in to the admin through a remember-me cookie as "admin@example.com" with password "Sylius1!"
        And the administrator "admin@example.com" already has 2FA enabled
        When I try to regenerate my admin recovery codes
        Then I should be on the admin login page
        And the administrator should have no recovery codes

    @ui
    Scenario: Under 2FA enforcement an administrator signed in through a remember-me cookie signs in again and sets up 2FA
        Given 2FA enforcement is enabled for admins
        And I am signed in to the admin through a remember-me cookie as "admin@example.com" with password "Sylius1!"
        When I visit the admin dashboard
        Then I should be on the admin login page
        When I sign in to the admin with email "admin@example.com" and password "Sylius1!"
        Then I should see the admin 2FA QR code
