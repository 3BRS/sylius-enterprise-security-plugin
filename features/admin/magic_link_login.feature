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
        And an admin magic link email should have been sent to "admin@example.com"

    @ui
    Scenario: Requesting a magic link for an unknown admin email does not leak that fact
        When I request an admin magic link for "unknown@example.com"
        Then I should see an admin magic link request confirmation
        And no admin magic link token should have been stored for "unknown@example.com"
        And no admin magic link email should have been sent to "unknown@example.com"

    @ui
    Scenario: The link in the email is the one that signs the administrator in
        When I request an admin magic link for "admin@example.com"
        And I follow the admin magic link from the email sent to "admin@example.com"
        Then I should be logged in as admin "admin@example.com"

    @ui @combination
    Scenario: Switching magic link off for customers leaves the admin one alone
        Given magic link is disabled for customers
        Then the customer magic link page should be gone
        And the admin magic link page should still be there

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
