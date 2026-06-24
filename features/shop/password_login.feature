@shop @password_login @ui
Feature: Global password login switch (shop)
    In order to push customers onto stronger sign-in methods
    As a store owner
    I want to turn email + password sign-in and registration off for the whole shop

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "customer@example.com" identified by "Password1!"

    Scenario: Customers can sign in with a password while the switch is on by default
        When I attempt to sign in with email "customer@example.com" and password "Password1!"
        Then I should be signed in to the shop

    Scenario: Password sign-in is not offered while the switch is off
        Given password login is disabled for customers
        When I visit the shop login page
        Then I should see the password-login-disabled notice
        And the password login form should be hidden
        And the forgot-password link should be hidden

    Scenario: The registration form is hidden while the switch is off
        Given password login is disabled for customers
        When I visit the shop registration page
        Then I should see the password-login-disabled notice

    Scenario: A registration request is blocked server-side while the switch is off
        Given password login is disabled for customers
        When I submit a registration request
        Then I should be redirected to the shop login page
        And I should see the registration-disabled error

    Scenario: The change-password entry disappears from the account while the switch is off
        Given I am logged in as "customer@example.com"
        And password login is disabled for customers
        When I visit my account dashboard
        Then the change-password entry should be hidden from my account
