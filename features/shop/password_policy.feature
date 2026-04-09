@shop @password_policy @ui
Feature: Customer registration password policy
    In order to keep customer accounts secure
    As a store owner
    I want to enforce a password policy during customer registration

    Background:
        Given the store operates on a single channel in "United States"

    @ui
    Scenario: Customer cannot register with a password that is too short
        Given I want to register a new account
        When I specify the first name as "John"
        And I specify the last name as "Doe"
        And I specify the email as "john@example.com"
        And I register with password "short"
        Then I should be notified that the password is too short

    @ui
    Scenario: Customer can register with a password that meets the minimum length
        Given I want to register a new account
        When I specify the first name as "John"
        And I specify the last name as "Doe"
        And I specify the email as "john@example.com"
        And I register with password "validpassword"
        Then I should be registered successfully

    @ui
    Scenario: Customer cannot register when uppercase letter is required but missing
        Given the store requires uppercase letters in customer passwords
        And I want to register a new account
        When I specify the first name as "John"
        And I specify the last name as "Doe"
        And I specify the email as "john@example.com"
        And I register with password "nouppercase1!"
        Then I should be notified that the password requires an uppercase letter

    @ui
    Scenario: Customer cannot register when a number is required but missing
        Given the store requires numbers in customer passwords
        And I want to register a new account
        When I specify the first name as "John"
        And I specify the last name as "Doe"
        And I specify the email as "john@example.com"
        And I register with password "NoNumbers!!"
        Then I should be notified that the password requires a number

    @ui
    Scenario: Customer cannot register when a special character is required but missing
        Given the store requires special characters in customer passwords
        And I want to register a new account
        When I specify the first name as "John"
        And I specify the last name as "Doe"
        And I specify the email as "john@example.com"
        And I register with password "NoSpecialChar1"
        Then I should be notified that the password requires a special character

    @ui
    Scenario: Customer can register with a strong password meeting all requirements
        Given the store requires uppercase letters in customer passwords
        And the store requires numbers in customer passwords
        And the store requires special characters in customer passwords
        And I want to register a new account
        When I specify the first name as "John"
        And I specify the last name as "Doe"
        And I specify the email as "john@example.com"
        And I register with password "StrongPass1!"
        Then I should be registered successfully
