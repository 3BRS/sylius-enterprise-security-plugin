@admin @magic_link @ui
Feature: Admin magic link login
    In order to sign in to the admin panel without typing a password
    As an administrator
    I want to receive a sign-in link by email

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password123!"

    @ui
    Scenario: Requesting a magic link for an existing admin shows a neutral confirmation
        When I request an admin magic link for "admin@example.com"
        Then I should see an admin magic link request confirmation
        And an admin magic link token should have been stored for "admin@example.com"

    @ui
    Scenario: Requesting a magic link for an unknown admin email does not leak that fact
        When I request an admin magic link for "unknown@example.com"
        Then I should see an admin magic link request confirmation
        And no admin magic link token should have been stored for "unknown@example.com"

    @ui
    Scenario: Following a valid admin magic link signs me in
        Given a valid admin magic link token "admin-valid-1" exists for "admin@example.com"
        When I follow the admin magic link "admin-valid-1"
        Then I should be logged in as admin "admin@example.com"

    @ui
    Scenario: Following an expired admin magic link fails
        Given an expired admin magic link token "admin-expired-1" exists for "admin@example.com"
        When I follow the admin magic link "admin-expired-1"
        Then I should see an admin magic link invalid-or-expired error

    @ui
    Scenario: Already-used admin magic link can no longer be used
        Given a used admin magic link token "admin-used-1" exists for "admin@example.com"
        When I follow the admin magic link "admin-used-1"
        Then I should see an admin magic link invalid-or-expired error

    @ui
    Scenario: Rate limit blocks additional admin requests within the window
        Given 3 admin magic link tokens have recently been issued for "admin@example.com"
        When I request an admin magic link for "admin@example.com"
        Then I should see an admin magic link request confirmation
        And exactly 3 admin magic link tokens should exist for "admin@example.com"
