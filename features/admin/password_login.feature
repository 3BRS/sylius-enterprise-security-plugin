@admin @password_login @ui
Feature: Global password login switch (admin)
    In order to keep admins and customers off passwords once the switch is flipped
    As a store owner
    I want password sign-in blocked and the password field gone from the admin-user and customer edit forms

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password1!"
        And there is a customer account "alice@example.com" identified by "Password1!"

    Scenario: The admin-user password field shows while admin password login is on
        When I am logged in as "admin@example.com" administrator
        And I open the admin user edit page for "admin@example.com"
        Then the admin-user password field should be visible

    Scenario: The admin-user password field is gone while admin password login is off
        Given password login is disabled for admins
        When I am logged in as "admin@example.com" administrator
        And I open the admin user edit page for "admin@example.com"
        Then the admin-user password field should be hidden

    Scenario: The customer password field shows while customer password login is on
        When I am logged in as "admin@example.com" administrator
        And I open the customer edit page for "alice@example.com"
        Then the customer password field should be visible

    Scenario: The customer password field is gone while customer password login is off
        Given password login is disabled for customers
        When I am logged in as "admin@example.com" administrator
        And I open the customer edit page for "alice@example.com"
        Then the customer password field should be hidden

    Scenario: An admin password sign-in is rejected at the authentication layer while the switch is off
        Given there is an administrator "signin-check@example.com" identified by "Password1!"
        When I visit the admin login page
        And password login is disabled for admins
        And I submit the admin login form with email "signin-check@example.com" and password "Password1!"
        Then I should not be signed in to the admin panel
