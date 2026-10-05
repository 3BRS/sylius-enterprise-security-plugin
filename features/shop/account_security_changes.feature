@shop @account_security_changes @ui
Feature: Account security changes from a customer sign-in
    In order to keep my second factor from being bypassed
    As a customer
    I want a sign-in waiting for its two-factor code kept from linking a social account, while a remembered sign-in can change my account security

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
    Scenario: A customer signed in through a remember-me cookie can link a social account
        Given I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        And the "google" OAuth provider will return user "g-remembered-1" with email "john@example.com"
        When I start linking my "google" account
        Then a social link should exist for "john@example.com" with "google" and provider id "g-remembered-1"

    @ui
    Scenario: A customer signed in through a remember-me cookie can add a passkey
        Given I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        When I request passkey registration options
        Then the passkey registration options should be issued
        When I submit a passkey registration
        Then the passkey registration should not be refused

    @ui
    Scenario: A customer signed in through a remember-me cookie can set up 2FA
        Given I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        When I visit the 2FA setup page
        Then I should see the 2FA QR code

    @ui
    Scenario: A customer signed in through a remember-me cookie can disable 2FA
        Given I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        And the customer "john@example.com" already has 2FA enabled with recovery codes
        When I visit the 2FA setup page
        And I press the 2FA disable button
        Then 2FA should not be enabled for "john@example.com"

    @ui
    Scenario: A customer signed in through a remember-me cookie can regenerate recovery codes
        Given I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        And the customer "john@example.com" already has 2FA enabled with recovery codes
        When I visit the 2FA setup page
        And I press the regenerate recovery codes button
        Then I should be on the 2FA recovery codes page
        And none of the previous recovery codes should work for "john@example.com"

    @ui
    Scenario: Under 2FA enforcement a customer signed in through a remember-me cookie sets up 2FA without signing in again
        Given 2FA enforcement is enabled for customers
        And I am signed in to the shop through a remember-me cookie as "john@example.com" with password "Password1!"
        When I visit the account dashboard
        Then I should be redirected to the 2FA setup page
        And I should see the 2FA QR code
