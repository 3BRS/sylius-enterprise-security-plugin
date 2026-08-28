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
        And the failed attempt counter for customer "customer@example.com" should be 5

    @ui @combination
    Scenario: A lockout threshold under the rate limit is the one that answers
        Given customer lockout trips after 3 failed sign-ins
        And the customer login rate limit allows 10 attempts per "15 minutes"
        When I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        Then customer "customer@example.com" should be locked
        When I try to sign in with email "customer@example.com" and password "Password1!"
        Then I should see the locked-account message
        And I should not see the too-many-requests message

    @ui @combination
    Scenario: A rate limit under the lockout threshold keeps the counter from ever reaching it
        # The trap this pins down: tightening the rate limit past the lockout
        # threshold switches lockout off in practice. The limiter refuses the attempt
        # before the failed-login counter is touched, so the account never locks and
        # an administrator watching the locked-customers page sees nothing.
        Given customer lockout trips after 10 failed sign-ins
        And the customer login rate limit allows 3 attempts per "15 minutes"
        When I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        Then I should be told there have been too many requests
        And customer "customer@example.com" should not be locked

    @ui @combination
    Scenario: At equal settings neither guard hides the other
        # What the plugin ships: lockout at five, the login rate limit at five. The
        # fifth failure locks the account and also spends the last of the budget, so
        # the sixth attempt is refused by the limiter - and the customer is told to
        # wait rather than that the account is locked. The lockout is real all the
        # same, which is what the administrator's locked-customers page goes by, so
        # this pins both halves rather than only the message.
        Given customer lockout trips after 5 failed sign-ins
        And the customer login rate limit allows 5 attempts per "15 minutes"
        When I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        Then customer "customer@example.com" should be locked
        When I try to sign in with email "customer@example.com" and password "Password1!"
        Then I should be told there have been too many requests

    @ui
    Scenario: Locked customer cannot sign in even with correct password
        Given customer "customer@example.com" is locked
        When I try to sign in with email "customer@example.com" and password "Password1!"
        Then I should see the locked-account message

    @ui
    Scenario: Lockout auto-expires when lockoutUntil is in the past
        Given customer "customer@example.com" was locked but the lockout has already expired
        When I sign in with email "customer@example.com" and password "Password1!"
        Then customer "customer@example.com" should not be locked
        And I should be signed in to the shop as "customer@example.com"

    @ui
    Scenario: Customer can sign in after the lockout auto-unlock interval has elapsed
        When I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        Then customer "customer@example.com" should be locked
        When the lockout time for customer "customer@example.com" has elapsed
        And I sign in with email "customer@example.com" and password "Password1!"
        Then I should be signed in to the shop as "customer@example.com"
        And customer "customer@example.com" should not be locked

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
