@admin @two_factor_login @ui
Feature: Administrator two-factor authentication login flow
    In order to protect my administrator account during login
    As an administrator with 2FA enabled
    I want to be prompted for a TOTP code after entering my password

    Background:
        Given there is an administrator "admin@example.com" identified by "Sylius1!"

    @ui
    Scenario: Administrator with 2FA enabled is redirected to the challenge page after login
        Given the administrator "admin@example.com" has 2FA enabled with a known secret
        And I want to log in
        When I specify the username as "admin@example.com"
        And I specify the password as "Sylius1!"
        And I log in
        Then I should be on the admin 2FA challenge page

    @ui
    Scenario: Administrator completes 2FA challenge with a valid TOTP code
        Given the administrator "admin@example.com" has 2FA enabled with a known secret
        And I want to log in
        When I specify the username as "admin@example.com"
        And I specify the password as "Sylius1!"
        And I log in
        And I submit a valid admin TOTP challenge code
        Then I should be fully authenticated as administrator

    @ui
    Scenario: Administrator submits an invalid code and stays on the challenge page
        Given the administrator "admin@example.com" has 2FA enabled with a known secret
        And I want to log in
        When I specify the username as "admin@example.com"
        And I specify the password as "Sylius1!"
        And I log in
        And I submit an invalid admin TOTP challenge code
        Then I should be on the admin 2FA challenge page
        And I should see an admin 2FA authentication error
