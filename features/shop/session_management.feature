@shop @session_management
Feature: Customer session management
    In order to be aware of and control where my account is signed in
    As a customer
    I want to see my active sessions, revoke them, and be notified about logins from new devices

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "alice@example.com" identified by "Password1!"

    @ui
    Scenario: Customer sees their current session in the list after logging in
        When I sign in with email "alice@example.com" and password "Password1!"
        And I visit my active sessions page
        Then I should see exactly 1 active session
        And I should see my current session marker

    @ui
    Scenario: Customer receives a login notification email on first login from a new device
        When I sign in with email "alice@example.com" and password "Password1!"
        Then a login notification email should have been sent to "alice@example.com"

    @ui
    Scenario: Customer does not receive a login notification email on a second login from the same device
        When I sign in with email "alice@example.com" and password "Password1!"
        And login notification emails are cleared again
        And I sign out from the shop
        And I sign in with email "alice@example.com" and password "Password1!"
        Then no login notification email should have been sent to "alice@example.com"

    @ui
    Scenario: Customer revokes another session and is signed out from it on the next request
        Given the customer "alice@example.com" has another active session "other-shop-session"
        When I sign in with email "alice@example.com" and password "Password1!"
        And I visit my active sessions page
        And I revoke the other shop session "other-shop-session"
        Then the shop session "other-shop-session" should be revoked

    @ui
    Scenario: Customer revokes all other sessions at once and only the current one remains active
        Given the customer "alice@example.com" has another active session "other-shop-session-a"
        And the customer "alice@example.com" has another active session "other-shop-session-b"
        When I sign in with email "alice@example.com" and password "Password1!"
        And I visit my active sessions page
        And I revoke all other shop sessions
        Then the shop session "other-shop-session-a" should be revoked
        And the shop session "other-shop-session-b" should be revoked

    @ui @combination
    Scenario: One customer cannot revoke another customer's session
        Given I sign in with email "alice@example.com" and password "Password1!"
        And the customer "alice@example.com" has another active session "alice-session-1"
        # Created only now: Sylius' account step swaps the password for a random one
        # and keeps the mapping in shared storage, so a second account made earlier
        # would leave the sign-in above reaching for the wrong secret.
        And there is a customer account "mallory@example.com" identified by "Password1!"
        And the customer "mallory@example.com" has another active session "mallory-session-1"
        When I visit my active sessions page
        And I try to revoke the session "mallory-session-1" that belongs to somebody else
        Then the revoke should have been refused as not found
        And the shop session "mallory-session-1" should still be active

    @ui
    Scenario: Customer is signed out when their current session is revoked externally
        When I sign in with email "alice@example.com" and password "Password1!"
        And the current shop session for "alice@example.com" is revoked externally
        And I visit my active sessions page
        Then I should be redirected to the shop login page
