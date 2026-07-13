@shop @account_deletion @ui
Feature: Customer self-service account deletion
    In order to exercise my GDPR right to erasure
    As a customer
    I want to delete my account, anonymizing my personal data after a grace period

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "delete-me@example.com" identified by "Password1!"
        And I am logged in as "delete-me@example.com"

    @ui
    Scenario: Customer requests account deletion via UI
        When I visit the account deletion page
        And I confirm account deletion with my password "Password1!"
        Then a deletion request should be scheduled for "delete-me@example.com"
        And the customer "delete-me@example.com" should be disabled

    @ui
    Scenario: Wrong password keeps the account active
        When I visit the account deletion page
        And I confirm account deletion with my password "WrongPassword!"
        Then no deletion request should exist for "delete-me@example.com"
        And the customer "delete-me@example.com" should be enabled

    @ui
    Scenario: With password login off the acknowledgement alone deletes the account
        Given password login is disabled for customers
        When I visit the account deletion page
        And I confirm account deletion without a password
        Then a deletion request should be scheduled for "delete-me@example.com"
        And the customer "delete-me@example.com" should be disabled

    @ui
    Scenario: Anonymization runs after the grace period elapses
        Given the customer "delete-me@example.com" requested deletion 60 days ago with grace 30
        When the account deletion process-due command runs
        Then the customer formerly known as "delete-me@example.com" should be anonymized
