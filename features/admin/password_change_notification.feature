@admin @password_change_notification @ui
Feature: Admin user password change notification
    In order to detect unauthorized account modifications
    As an administrator
    I want to receive an email notification when my password is changed

    Background:
        Given the store operates on a single channel in "United States"
        And all password change notification emails are cleared

    @ui
    Scenario: Admin receives notification email when their password is changed
        Given there is an administrator "admin@example.com" identified by "Sylius1!Pass"
        And the administrator "admin@example.com" has the password "Sylius1!Pass" set directly
        When the administrator "admin@example.com" password is changed to "NewAdmin1!Pass" directly
        Then a password change notification email should have been sent to admin "admin@example.com"

    @ui
    Scenario: Admin receives notification email when another administrator changes their password
        Given there is an administrator "alice@example.com" identified by "Alice1!Pass"
        And there is an administrator "bob@example.com" identified by "Bob1!Password"
        And the administrator "alice@example.com" has the password "Alice1!Pass" set directly
        And the administrator "bob@example.com" has the password "Bob1!Password" set directly
        And I am logged in as "alice@example.com" administrator
        And all password change notification emails are cleared
        When I edit administrator "bob@example.com"
        And I change their password to "NewBob1!Password"
        And I save my changes
        Then a password change notification email should have been sent to admin "bob@example.com"
        And no password change notification email should have been sent to admin "alice@example.com"
