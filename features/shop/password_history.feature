@shop @password_history @ui
Feature: Customer password history
    In order to keep customer accounts secure
    As a store owner
    I want to prevent customers from reusing recently used passwords

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "CurrentPass1!"
        And I am logged in as "john@example.com"

    @ui
    Scenario: Customer cannot reuse a password from their history
        Given the password "OldPass1!" is in the history of customer "john@example.com"
        When I want to change my password
        And I change my password from "CurrentPass1!" to "OldPass1!"
        Then I should be notified that this password was recently used

    @ui
    Scenario: Customer can use a new password not in their history
        Given the password "OldPass1!" is in the history of customer "john@example.com"
        When I want to change my password
        And I change my password from "CurrentPass1!" to "BrandNewPass1!"
        Then my password should be changed successfully

    @ui
    Scenario: Customer cannot change password to one too similar to the current
        When I want to change my password
        And I change my password from "CurrentPass1!" to "CurrentPass1!extra"
        Then I should be notified that the new password is too similar to the current one
