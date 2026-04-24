@admin @password_history @ui
Feature: Admin user password history
    In order to keep administrator accounts secure
    As a store owner
    I want to prevent administrators from reusing recently used passwords

    Background:
        Given I am logged in as an administrator

    @ui
    Scenario: Administrator cannot reuse a password from their history
        Given the password "OldAdminPass1!" is in the history of administrator "sylius@example.com"
        When I want to edit this administrator
        And I change the administrator password to "OldAdminPass1!"
        Then I should be notified that this password was recently used

    @ui
    Scenario: Administrator can use a new password not in their history
        Given the password "OldAdminPass1!" is in the history of administrator "sylius@example.com"
        When I want to edit this administrator
        And I change the administrator password to "BrandNewAdminPass1!"
        Then the administrator should be saved successfully
