@admin @customer_management @ui
Feature: Admin can manage customer security from the customer detail page
    In order to handle customer support and incident response
    As an administrator
    I want to force a password reset, block / unblock the account, and manage active sessions from one place

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password1!"
        And there is a customer account "alice@example.com" identified by "Password1!"

    Scenario: Admin sees the security section on the customer detail page
        When I am logged in as "admin@example.com" administrator
        And I open the customer detail page for "alice@example.com"
        Then I should see the customer security section
        And I should see the login history table

    Scenario: Admin forces a password reset
        When I am logged in as "admin@example.com" administrator
        And I force a password reset for customer "alice@example.com"
        Then customer "alice@example.com" should be required to change their password on next sign-in

    Scenario: Admin blocks a customer
        Given the customer "alice@example.com" has an active session from "198.51.100.50"
        When I am logged in as "admin@example.com" administrator
        And I block customer "alice@example.com" from the admin panel
        Then customer "alice@example.com" should be blocked
        And customer "alice@example.com" should have 0 active sessions

    Scenario: Admin revokes all customer sessions
        Given the customer "alice@example.com" has an active session from "198.51.100.50"
        And the customer "alice@example.com" has an active session from "203.0.113.10"
        When I am logged in as "admin@example.com" administrator
        And I revoke all sessions for customer "alice@example.com"
        Then customer "alice@example.com" should have 0 active sessions

    Scenario: Admin revokes a single customer session
        Given the customer "alice@example.com" has an active session from "198.51.100.50"
        And the customer "alice@example.com" has an active session from "203.0.113.10"
        When I am logged in as "admin@example.com" administrator
        And I revoke the first active session for customer "alice@example.com"
        Then customer "alice@example.com" should have 1 active session
