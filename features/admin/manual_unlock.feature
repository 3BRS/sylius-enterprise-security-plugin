@admin @lockout @ui
Feature: Admin manual unlock
    In order to recover locked accounts
    As an administrator
    I want to manually unlock locked customers and admins from the admin panel

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password1!"
        And I am logged in as "admin@example.com" administrator
        And there is a customer account "customer@example.com" identified by "Password1!"

    @ui
    Scenario: Admin sees no locked customers when none are locked
        When I visit the locked customers page
        Then I should see no locked customers

    @ui
    Scenario: Admin can manually unlock a locked customer
        Given customer "customer@example.com" is locked
        When I visit the locked customers page
        And I unlock the locked customer "customer@example.com"
        Then customer "customer@example.com" should not be locked
        And I should see no locked customers

    @ui
    Scenario: Admin can manually unlock another locked admin
        Given admin "admin@example.com" is locked
        When I visit the locked admins page
        And I unlock the locked admin "admin@example.com"
        Then admin "admin@example.com" should not be locked
