@admin @account_deletion @ui
Feature: Admin can cancel pending account deletion requests
    In order to honour customer-support requests after a deletion was initiated
    As an administrator
    I want to view pending deletion requests and cancel them before the grace period elapses

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password1!"
        And there is a customer account "delete-me@example.com" identified by "Password1!"
        And the customer "delete-me@example.com" requested deletion 1 days ago with grace 30
        And I am logged in as "admin@example.com" administrator

    @ui
    Scenario: Admin sees the pending request and cancels it
        When I open the account deletions admin page
        Then I should see "delete-me@example.com" in the pending deletions list
        When I cancel the deletion request for "delete-me@example.com"
        Then no deletion request should be pending for "delete-me@example.com"
        And the customer "delete-me@example.com" should be enabled
