@shop @account_security_changes @ui
Feature: Account security changes need a full customer sign-in
    In order to keep my second factor from being bypassed
    As a customer
    I want linking a social account, adding a passkey and managing 2FA to need a full sign-in

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "Password1!"

    @ui @T76
    Scenario: A sign-in waiting for its two-factor code cannot link a social account
        Given the customer "john@example.com" has 2FA enabled with a known secret
        And the "google" OAuth provider will return user "g-pending-1" with email "john@example.com"
        When I sign in to the shop with email "john@example.com" and password "Password1!"
        And I start linking my "google" account
        Then I should be on the 2FA challenge page
        And the customer "john@example.com" should not be linked to "google"

    @ui
    Scenario: A sign-in waiting for its two-factor code can sign in through a linked provider instead
        Given the customer "john@example.com" has 2FA enabled with a known secret
        And the customer "john@example.com" is already linked to "google" with id "g-linked-1"
        And the "google" OAuth provider will return user "g-linked-1" with email "john@example.com"
        When I sign in to the shop with email "john@example.com" and password "Password1!"
        And I click the "google" social login button
        Then I should be fully authenticated

    @ui
    Scenario: A customer who entered the password and the code can link a social account
        Given the customer "john@example.com" has 2FA enabled with a known secret
        And the "google" OAuth provider will return user "g-full-1" with email "john@example.com"
        When I sign in to the shop with email "john@example.com" and password "Password1!"
        And I submit a valid TOTP challenge code
        And I start linking my "google" account
        Then a social link should exist for "john@example.com" with "google" and provider id "g-full-1"

    @ui
    Scenario: A customer signed in through a remember-me cookie cannot link a social account
        Given I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        And the "google" OAuth provider will return user "g-remembered-1" with email "john@example.com"
        When I start linking my "google" account
        Then I should be on the shop login page
        And the customer "john@example.com" should not be linked to "google"

    @ui
    Scenario: A customer signed in through a remember-me cookie cannot add a passkey
        Given I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        When I request passkey registration options
        Then the passkey registration should be refused
        When I submit a passkey registration
        Then the passkey registration should be refused

    @ui
    Scenario: A fully signed-in customer gets passkey registration options
        Given I am logged in as "john@example.com"
        When I request passkey registration options
        Then the passkey registration options should be issued

    @ui
    Scenario: A customer signed in through a remember-me cookie signs in again before the 2FA setup and returns to it
        Given I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        When I visit the 2FA setup page
        Then I should be on the shop login page
        When I sign in to the shop with email "john@example.com" and password "Password1!"
        Then I should see the 2FA QR code

    @ui
    Scenario: A customer signed in through a remember-me cookie cannot disable 2FA
        Given I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        And the customer "john@example.com" already has 2FA enabled
        When I try to disable two-factor authentication
        Then I should be on the shop login page
        And 2FA should be enabled for "john@example.com"

    @ui
    Scenario: A customer signed in through a remember-me cookie cannot regenerate recovery codes
        Given I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        And the customer "john@example.com" already has 2FA enabled
        When I try to regenerate my recovery codes
        Then I should be on the shop login page
        And the customer should have no recovery codes

    @ui
    Scenario: Under 2FA enforcement a customer signed in through a remember-me cookie signs in again and sets up 2FA
        Given 2FA enforcement is enabled for customers
        And I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        When I visit the account dashboard
        Then I should be on the shop login page
        When I sign in to the shop with email "john@example.com" and password "Password1!"
        Then I should see the 2FA QR code
