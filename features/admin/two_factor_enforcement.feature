@admin @two_factor_enforcement @ui
Feature: Admin two-factor authentication enforcement
    In order to protect all admin accounts
    As a store owner
    I want to force admins to enable 2FA before using the admin panel

    Background:
        Given there is an administrator "admin@example.com"
        And 2FA enforcement is enabled for admins

    @ui
    Scenario: Admin without 2FA is redirected to the setup page after login
        Given I am logged in as "admin@example.com" administrator
        When I visit the admin dashboard
        Then I should be redirected to the admin 2FA setup page
