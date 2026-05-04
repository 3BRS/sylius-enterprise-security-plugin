@admin @session_management
Feature: Admin session management
    In order to be aware of and control where my admin account is signed in
    As an administrator
    I want to see my active sessions, revoke them, and be notified about logins from new devices

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password1!"

    @ui
    Scenario: Admin sees their current session in the list after logging in
        When I sign in to the admin panel with email "admin@example.com" and password "Password1!"
        And I visit the admin active sessions page
        Then I should see exactly 1 active admin session
        And I should see my current admin session marker

    @ui
    Scenario: Admin receives a login notification email on first login from a new device
        When I sign in to the admin panel with email "admin@example.com" and password "Password1!"
        Then a login notification email should have been sent to "admin@example.com"

    @ui
    Scenario: Admin does not receive a login notification email on a second login from the same device
        When I sign in to the admin panel with email "admin@example.com" and password "Password1!"
        And login notification emails are cleared again
        And I sign out from the admin panel
        And I sign in to the admin panel with email "admin@example.com" and password "Password1!"
        Then no login notification email should have been sent to "admin@example.com"

    @ui
    Scenario: Admin revokes another session and it is marked revoked
        Given the admin "admin@example.com" has another active session "other-admin-session"
        When I sign in to the admin panel with email "admin@example.com" and password "Password1!"
        And I visit the admin active sessions page
        And I revoke the other admin session "other-admin-session"
        Then the admin session "other-admin-session" should be revoked

    @ui
    Scenario: Admin revokes all other sessions at once and only the current one remains active
        Given the admin "admin@example.com" has another active session "other-admin-session-a"
        And the admin "admin@example.com" has another active session "other-admin-session-b"
        When I sign in to the admin panel with email "admin@example.com" and password "Password1!"
        And I visit the admin active sessions page
        And I revoke all other admin sessions
        Then the admin session "other-admin-session-a" should be revoked
        And the admin session "other-admin-session-b" should be revoked

    @ui
    Scenario: Admin is signed out when their current session is revoked externally
        When I sign in to the admin panel with email "admin@example.com" and password "Password1!"
        And the current admin session for "admin@example.com" is revoked externally
        And I visit the admin active sessions page
        Then I should be redirected to the admin login page
