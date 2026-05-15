@shop @magic_link @ui
Feature: Customer magic link login
    In order to sign in without typing a password
    As a customer
    I want to receive a sign-in link by email

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "existing@example.com" identified by "Password1!"

    @ui
    Scenario: Requesting a magic link for an existing account shows a neutral confirmation
        When I request a magic link for "existing@example.com"
        Then I should see a magic link request confirmation
        And a magic link token should have been stored for "existing@example.com"

    @ui
    Scenario: Requesting a magic link for an unknown email does not leak that fact
        When I request a magic link for "unknown@example.com"
        Then I should see a magic link request confirmation
        And no magic link token should have been stored for "unknown@example.com"

    @ui
    Scenario: Following a valid magic link signs me in
        Given a valid magic link token "shop-valid-1" exists for "existing@example.com"
        When I follow the magic link "shop-valid-1"
        Then I should be logged in as "existing@example.com"

    @ui
    Scenario: Following an expired magic link fails
        Given an expired magic link token "shop-expired-1" exists for "existing@example.com"
        When I follow the magic link "shop-expired-1"
        Then I should see a magic link invalid-or-expired error

    @ui
    Scenario: Already-used magic link can no longer be used
        Given a used magic link token "shop-used-1" exists for "existing@example.com"
        When I follow the magic link "shop-used-1"
        Then I should see a magic link invalid-or-expired error
