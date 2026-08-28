@admin @ip_whitelist @ui
Feature: Admin panel can be restricted by IP whitelist
    In order to limit who can reach the admin panel
    As an administrator
    I want to control admin access by IP address — globally and per-administrator

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password1!"
        And there is an administrator "other@example.com" identified by "Password1!"

    Scenario: Admin can sign in when the IP whitelist feature is disabled
        Given the admin IP whitelist is disabled
        When I am logged in as "admin@example.com" administrator
        And I open any admin page
        Then the admin response status should be 200

    Scenario: Admin from a globally allowed IP can reach the admin panel
        Given the admin IP whitelist is enabled with global CIDRs "127.0.0.1, 10.0.0.0/8"
        When I am logged in as "admin@example.com" administrator
        And I open any admin page
        Then the admin response status should be 200

    Scenario: Admin from a non-allowed IP is rejected with 403
        Given the admin IP whitelist is enabled with global CIDRs "203.0.113.0/24"
        When I open any admin page
        Then the admin response status should be 403

    Scenario: Per-admin whitelist grants access when the global list does not match
        Given the admin IP whitelist is enabled with global CIDRs "203.0.113.0/24"
        And administrator "admin@example.com" has per-admin IP whitelist enabled with CIDRs "127.0.0.1"
        When I am logged in as "admin@example.com" administrator
        And I open any admin page
        Then the admin response status should be 200

    Scenario: Pure per-admin (no global) allows reaching the login form and signing in
        Given the admin IP whitelist is enabled with no global CIDRs
        And administrator "admin@example.com" has per-admin IP whitelist enabled with CIDRs "127.0.0.1"
        When I am logged in as "admin@example.com" administrator
        And I open any admin page
        Then the admin response status should be 200

    # The 200 above is also what an unenforced whitelist returns, so it says nothing on
    # its own. This is its other half: from the same address, the administrator whose
    # own list does not cover it is refused, which is what makes the entry the reason.
    Scenario: An administrator whose own list does not cover the address is refused
        Given the admin IP whitelist is enabled with no global CIDRs
        And administrator "admin@example.com" has per-admin IP whitelist enabled with CIDRs "127.0.0.1"
        When I am logged in as "other@example.com" administrator
        And I open any admin page
        Then the admin response status should be 403

    Scenario: Disabled per-admin entry is ignored
        Given the admin IP whitelist is enabled with global CIDRs "203.0.113.0/24"
        And administrator "admin@example.com" has per-admin IP whitelist disabled with CIDRs "127.0.0.1"
        When I open any admin page
        Then the admin response status should be 403

    Scenario: Sign-in itself is refused from a non-allowed IP
        Given the admin IP whitelist is enabled with global CIDRs "203.0.113.0/24"
        When I submit the admin sign-in form as "admin@example.com" with "Password1!"
        Then the admin response status should be 403

    @combination
    Scenario: A magic link does not carry an administrator past the IP whitelist
        Given a valid admin magic link token "whitelist-1" exists for "admin@example.com"
        And the admin IP whitelist is enabled with global CIDRs "203.0.113.0/24"
        When I follow the admin magic link "whitelist-1"
        Then the admin response status should be 403
        And the admin magic link "whitelist-1" should still be unused
        Given the admin IP whitelist is disabled
        When I follow the admin magic link "whitelist-1"
        Then I should be logged in as admin "admin@example.com"

    Scenario: Admin can list IP whitelist administrators
        Given the admin IP whitelist is enabled with global CIDRs "127.0.0.1"
        When I am logged in as "admin@example.com" administrator
        And I open the IP whitelist admins page
        Then the admin response status should be 200
        And I should see the IP whitelist admins list
