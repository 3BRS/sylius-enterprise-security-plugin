@shop @password_expiration @ui
Feature: Customer password expiration
    In order to keep customer accounts secure
    As a store owner
    I want to force customers to change their expired passwords

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "Password1!"

    @ui
    Scenario: Customer with expired password is redirected to change password page
        Given the customer "john@example.com" has an expired password
        And I am logged in as "john@example.com"
        When I try to open the account page
        Then I should be on the change password page

    @ui
    Scenario: Customer without expired password can access their account normally
        And I am logged in as "john@example.com"
        When I try to open the account page
        Then I should not be redirected to the change password page
