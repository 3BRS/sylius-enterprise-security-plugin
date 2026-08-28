@shop @two_factor_enforcement @ui
Feature: Customer two-factor authentication enforcement
    In order to protect all customer accounts
    As a store owner
    I want to force customers to enable 2FA before using the shop

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "Password1!"
        And 2FA enforcement is enabled for customers

    @ui
    Scenario: Customer without 2FA is redirected to the setup page
        Given I am logged in as "john@example.com"
        When I visit the account dashboard
        Then I should be redirected to the 2FA setup page

    @ui @combination
    Scenario: Enforcement still leads somewhere when password login is off
        Given password login is disabled for customers
        When I request a magic link for "john@example.com"
        And I follow the magic link from the email sent to "john@example.com"
        And I visit the account dashboard
        Then I should be redirected to the 2FA setup page
        When I visit the 2FA setup page
        Then I should see the 2FA QR code

    @ui
    Scenario: Customer with 2FA enabled is not redirected
        Given the customer "john@example.com" already has 2FA enabled
        And I am logged in as "john@example.com"
        When I visit the account dashboard
        Then I should remain on the account dashboard
