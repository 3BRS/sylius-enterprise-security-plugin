@shop @set_password @ui
Feature: Setting a password for a passwordless account
    In order to also sign in with my email and password
    As a customer who registered without a password
    I want to set a password on my account

    Background:
        Given the store operates on a single channel in "United States"
        And there is a passwordless customer account "oauth@example.com"
        And I am logged in as "oauth@example.com"

    @ui
    Scenario: Passwordless customer sets a password without confirming a current one
        When I visit the set password page
        And I set my new password to "NewStrongPass1!"
        Then my password should be set successfully
        And the customer "oauth@example.com" should be able to sign in with password "NewStrongPass1!"

    @ui
    Scenario: The account offers to set a password instead of changing it
        When I visit my account dashboard
        Then I should be offered to set a new password

    @ui
    Scenario: A customer with password login disabled sees no password option
        Given password login is disabled for the customer "oauth@example.com"
        When I visit my account dashboard
        Then I should not see any password management option
