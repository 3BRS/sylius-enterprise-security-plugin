@admin @password_policy @ui @customer_edit
Feature: Admin editing customer password policy
    In order to keep customer accounts secure
    As an administrator
    I want the password policy to be enforced when I edit a customer's password

    Background:
        Given I am logged in as an administrator
        And the store has customer "john@example.com"

    @ui
    Scenario: Admin cannot set a customer password that is too short
        When I change the password of customer "john@example.com" to "short"
        Then I should be notified that the customer password is too short

    @ui
    Scenario: Admin cannot set a customer password without an uppercase letter
        When I change the password of customer "john@example.com" to "nouppercase1!"
        Then I should be notified that the customer password requires an uppercase letter

    @ui
    Scenario: Admin cannot set a customer password without a number
        When I change the password of customer "john@example.com" to "NoNumbers!!"
        Then I should be notified that the customer password requires a number

    @ui
    Scenario: Admin cannot set a customer password without a special character
        When I change the password of customer "john@example.com" to "NoSpecialChar1"
        Then I should be notified that the customer password requires a special character

    @ui
    Scenario: Admin can set a customer password that meets all requirements
        When I change the password of customer "john@example.com" to "StrongPass1!"
        Then the customer should be saved successfully

    @ui
    Scenario: Admin can set a customer password with exactly the minimum required length
        When I change the password of customer "john@example.com" to "Passw0r!"
        Then the customer should be saved successfully
