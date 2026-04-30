@shop @lockout @ui
Feature: Customer account lockout
    In order to protect customer accounts from brute-force attacks
    As a customer
    I want my account to be locked after a configurable number of failed sign-in attempts

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "customer@example.com" identified by "Password1!"

    @ui
    Scenario: Customer account is locked after the configured number of failed attempts
        When I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        Then customer "customer@example.com" should be locked

    @ui
    Scenario: Locked customer cannot sign in even with correct password
        Given customer "customer@example.com" is locked
        When I try to sign in with email "customer@example.com" and password "Password1!"
        Then I should see the locked-account message

    @ui
    Scenario: Lockout auto-expires when lockoutUntil is in the past
        Given customer "customer@example.com" was locked but the lockout has already expired
        When I try to sign in with email "customer@example.com" and password "Password1!"
        Then customer "customer@example.com" should not be locked

    @ui
    Scenario: Admin manual unlock allows immediate sign-in
        Given customer "customer@example.com" is locked
        When the locked customer "customer@example.com" is unlocked by an administrator
        And I sign in with email "customer@example.com" and password "Password1!"
        Then I should be signed in to the shop as "customer@example.com"

    @ui
    Scenario: Customer locked + rate-limited can sign in immediately after admin unlock
        When I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        Then customer "customer@example.com" should be locked
        When the locked customer "customer@example.com" is unlocked by an administrator
        And I sign in with email "customer@example.com" and password "Password1!"
        Then I should not see the too-many-requests message
        And I should be signed in to the shop as "customer@example.com"
