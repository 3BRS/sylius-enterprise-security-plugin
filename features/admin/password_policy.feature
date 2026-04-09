@admin @password_policy @ui
Feature: Admin user password policy
    In order to keep administrator accounts secure
    As a store owner
    I want to enforce a stricter password policy for admin users

    Background:
        Given I am logged in as an administrator

    @ui
    Scenario: Administrator cannot be created with a password that is too short
        Given I want to create a new administrator
        When I specify its email as "admin@example.com"
        And I specify its name as "newadmin"
        And I create an administrator with password "short"
        Then I should be notified that the password is too short

    @ui
    Scenario: Administrator cannot be created without an uppercase letter
        Given I want to create a new administrator
        When I specify its email as "admin@example.com"
        And I specify its name as "newadmin"
        And I create an administrator with password "nouppercase1!"
        Then I should be notified that the password requires an uppercase letter

    @ui
    Scenario: Administrator cannot be created without a lowercase letter
        Given I want to create a new administrator
        When I specify its email as "admin@example.com"
        And I specify its name as "newadmin"
        And I create an administrator with password "NOLOWERCASE1!"
        Then I should be notified that the password requires a lowercase letter

    @ui
    Scenario: Administrator cannot be created without a number
        Given I want to create a new administrator
        When I specify its email as "admin@example.com"
        And I specify its name as "newadmin"
        And I create an administrator with password "NoNumbers!!"
        Then I should be notified that the password requires a number

    @ui
    Scenario: Administrator cannot be created without a special character
        Given I want to create a new administrator
        When I specify its email as "admin@example.com"
        And I specify its name as "newadmin"
        And I create an administrator with password "NoSpecialChar1"
        Then I should be notified that the password requires a special character

    @ui
    Scenario: Administrator can be created with a strong password
        Given I want to create a new administrator
        When I specify its email as "admin@example.com"
        And I specify its name as "newadmin"
        And I create an administrator with password "StrongAdminPass1!"
        Then the administrator should be saved successfully

    @ui
    Scenario: Administrator password can be changed to a strong password
        Given there is an administrator "existing@example.com" identified by "sylius"
        When I want to edit this administrator
        And I change the administrator password to "NewStrongPass1!"
        Then the administrator should be saved successfully
