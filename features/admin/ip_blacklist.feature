@admin @ip_blacklist @ui
Feature: Admin panel can be restricted by IP blacklist
    In order to block specific IPs or networks from reaching the admin panel
    As an administrator
    I want to control admin access by a global IP blacklist

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password1!"

    Scenario: Admin can sign in when the IP blacklist feature is disabled
        Given the admin IP blacklist is disabled
        When I am logged in as "admin@example.com" administrator
        And I open any admin page
        Then the admin response status should be 200

    Scenario: Admin from a non-blacklisted IP can reach the admin panel
        Given the admin IP blacklist is enabled with global CIDRs "203.0.113.0/24"
        When I am logged in as "admin@example.com" administrator
        And I open any admin page
        Then the admin response status should be 200

    Scenario: Admin from a globally blacklisted IP is rejected with 403
        Given the admin IP blacklist is enabled with global CIDRs "127.0.0.1, 10.0.0.0/8"
        When I open any admin page
        Then the admin response status should be 403

    Scenario: Empty blacklist with feature enabled allows everything (fail-open)
        Given the admin IP blacklist is enabled with no global CIDRs
        When I am logged in as "admin@example.com" administrator
        And I open any admin page
        Then the admin response status should be 200
