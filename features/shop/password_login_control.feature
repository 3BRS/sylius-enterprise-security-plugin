@shop @password_login_control @ui
Feature: Per-user password login control (shop)
    In order to force selected customers onto stronger sign-in methods
    As a store administrator
    I want to disable email + password sign-in for individual customers

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "customer@example.com" identified by "Password1!"

    @ui
    Scenario: Customer with password login disabled cannot sign in with a password
        Given password login is disabled for customer "customer@example.com"
        When I try to sign in with email "customer@example.com" and password "Password1!"
        Then I should not be signed in to the shop
        And I should see the password-login-disabled message

    @ui
    Scenario: Customer with password login allowed can still sign in
        When I sign in with email "customer@example.com" and password "Password1!"
        Then I should be signed in to the shop as "customer@example.com"
