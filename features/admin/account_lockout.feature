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

    @ui @combination
    Scenario: The lockout answers before the rate limit does
        # Lockout trips at 3 for administrators, the login rate limit at 5, so the
        # fourth attempt is inside the rate limit and outside the lockout. Whichever
        # of the two answers, it must not answer for the other.
        When I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        Then admin "admin@example.com" should be locked
        When I try to sign in to the admin panel with email "admin@example.com" and password "Password1!"
        Then I should see the locked-account message
        And I should not see the too-many-requests message

    @ui
    Scenario: Locked admin cannot sign in even with correct password
        Given admin "admin@example.com" is locked
        When I try to sign in to the admin panel with email "admin@example.com" and password "Password1!"
        Then I should see the locked-account message

    @ui
    Scenario: Lockout auto-expires when lockoutUntil is in the past
        Given admin "admin@example.com" was locked but the lockout has already expired
        When I sign in to the admin panel with email "admin@example.com" and password "Password1!"
        Then admin "admin@example.com" should not be locked
        And I should be signed in to the admin panel as "admin@example.com"

    @ui
    Scenario: Admin can sign in after the lockout auto-unlock interval has elapsed
        When I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        Then admin "admin@example.com" should be locked
        When the lockout time for admin "admin@example.com" has elapsed
        And I sign in to the admin panel with email "admin@example.com" and password "Password1!"
        Then I should be signed in to the admin panel as "admin@example.com"
        And admin "admin@example.com" should not be locked

    @ui
    Scenario: Admin locked + rate-limited can sign in immediately after another administrator's unlock
        When I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        Then admin "admin@example.com" should be locked
        When the locked admin "admin@example.com" is unlocked by another administrator
        And I sign in to the admin panel with email "admin@example.com" and password "Password1!"
        Then I should not see the too-many-requests message
        And I should be signed in to the admin panel as "admin@example.com"
