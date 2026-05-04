@admin @lockout @ui
Feature: Admin account lockout
    In order to protect administrator accounts from brute-force attacks
    As a store owner
    I want admin logins to be locked after a configurable number of failed attempts

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password1!"

    @ui
    Scenario: Admin account is locked after the configured number of failed attempts
        When I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        Then admin "admin@example.com" should be locked
        And the failed attempt counter for admin "admin@example.com" should be 3

    @ui
    Scenario: Locked admin cannot sign in even with correct password
        Given admin "admin@example.com" is locked
        When I try to sign in to the admin panel with email "admin@example.com" and password "Password1!"
        Then I should see the locked-account message

    @ui
    Scenario: Lockout auto-expires when lockoutUntil is in the past
        Given admin "admin@example.com" was locked but the lockout has already expired
        When I try to sign in to the admin panel with email "admin@example.com" and password "Password1!"
        Then admin "admin@example.com" should not be locked
