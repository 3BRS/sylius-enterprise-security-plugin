@admin @password_expiration @ui
Feature: Admin user password expiration
    In order to keep administrator accounts secure
    As a store owner
    I want to force administrators to change their expired passwords

    Background:
        Given there is an administrator "admin@example.com" identified by "Sylius1!"

    @ui
    Scenario: Administrator with forced password change is redirected after login
        Given the administrator "admin@example.com" is forced to change their password
        And I am logged in as "admin@example.com" administrator
        When I try to open the admin dashboard
        Then I should be on the admin force password change page

    @ui
    Scenario: Administrator without forced password change can access dashboard normally
        Given I am logged in as "admin@example.com" administrator
        When I try to open the admin dashboard
        Then I should not be on the admin force password change page

    @ui @combination
    Scenario: A forced change still refuses a password from the history
        Given the administrator "admin@example.com" is forced to change their password
        And the password "OldSylius1!" is in the history of administrator "admin@example.com"
        And I am logged in as "admin@example.com" administrator
        And I try to open the admin dashboard
        When I submit the force password change form with current password "Sylius1!" and new password "OldSylius1!"
        Then I should be notified that this password was recently used
        And administrator "admin@example.com" should be forced to change their password on next login

    @ui
    Scenario: Administrator can force another administrator to change their password
        Given I am logged in as an administrator
        And there is an administrator "target@example.com" identified by "Password1!"
        When I want to edit administrator "target@example.com"
        And I check the force password change checkbox
        And I save my changes
        Then administrator "target@example.com" should be forced to change their password on next login

    @ui
    Scenario: Force password change flag is cleared after administrator changes their password
        Given the administrator "admin@example.com" is forced to change their password
        And I am logged in as "admin@example.com" administrator
        And I try to open the admin dashboard
        When I submit the force password change form with current password "Sylius1!" and new password "NewAdminPass1!"
        Then administrator "admin@example.com" should not be forced to change their password anymore
