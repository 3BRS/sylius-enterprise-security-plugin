@shop @password_change_notification @ui
Feature: Customer password change notification
    In order to detect unauthorized access
    As a customer
    I want to receive an email notification when my password is changed

    Background:
        Given the store operates on a single channel in "United States"
        And all password change notification emails are cleared

    @ui
    Scenario: Customer receives notification email after changing their password
        Given there is a customer account "john@example.com" identified by "Password1!"
        And the customer "john@example.com" has the password "Password1!" set directly
        And I am logged in as "john@example.com"
        When I want to change my password
        And I change my password from "Password1!" to "NewPassword1!"
        Then a password change notification email should have been sent to "john@example.com"

    @ui
    Scenario: Customer receives notification email after resetting their forgotten password
        Given there is a customer account "john@example.com" identified by "Password1!"
        And the customer "john@example.com" has the password "Password1!" set directly
        And a password reset token "resetMe1234" has been issued to "john@example.com"
        When I reset my password using token "resetMe1234" to "NewPassword1!"
        Then a password change notification email should have been sent to "john@example.com"
