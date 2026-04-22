@shop @password_policy @ui @change_password
Feature: Customer change password policy
    In order to keep customer accounts secure
    As a logged in customer
    I want the password policy to be enforced when I change my password

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "Password1!"
        And I am logged in as "john@example.com"

    @ui
    Scenario: Customer cannot change password to one that is too short
        When I want to change my password
        And I change my password from "Password1!" to "short"
        Then I should be notified that the new password is too short

    @ui
    Scenario: Customer can change password to one that meets the minimum length
        When I want to change my password
        And I change my password from "Password1!" to "validpassword"
        Then my password should be changed successfully

    @ui
    Scenario: Customer cannot change password without an uppercase letter when required
        When I want to change my password
        And I change my password from "Password1!" to "nouppercase1!"
        Then I should be notified that the new password requires an uppercase letter

    @ui
    Scenario: Customer cannot change password without a number when required
        When I want to change my password
        And I change my password from "Password1!" to "NoNumbers!!"
        Then I should be notified that the new password requires a number

    @ui
    Scenario: Our policy error replaces the Sylius built-in minimum length error for very short new password
        When I want to change my password
        And I change my password from "Password1!" to "ab"
        Then I should be notified that the new password must be at least 8 characters
        And the Sylius built-in minimum password length error should not appear

    @ui
    Scenario: Customer cannot change password without a special character when required
        When I want to change my password
        And I change my password from "Password1!" to "NoSpecialChar1"
        Then I should be notified that the new password requires a special character
