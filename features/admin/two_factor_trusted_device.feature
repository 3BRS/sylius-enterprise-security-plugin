@admin @two_factor_trusted_device @ui
Feature: Administrator two-factor authentication trusted-device skip
    In order to avoid entering a TOTP code on every login from the same browser
    As an administrator with 2FA enabled
    I want to mark a browser as trusted during the 2FA challenge

    Background:
        Given there is an administrator "admin@example.com" identified by "Sylius1!"

    @ui
    Scenario: Administrator marks device as trusted and skips 2FA on subsequent login
        Given the administrator "admin@example.com" has 2FA enabled with a known secret
        And I want to log in
        When I specify the username as "admin@example.com"
        And I specify the password as "Sylius1!"
        And I log in
        And I submit a valid admin TOTP challenge code trusting this device
        Then I should be fully authenticated as administrator
        When I sign out from the admin panel
        And I want to log in
        And I specify the username as "admin@example.com"
        And I specify the password as "Sylius1!"
        And I log in
        Then I should be fully authenticated as administrator

    @ui
    Scenario: Trusted device cookie is revoked after bumping the administrator's token version
        Given the administrator "admin@example.com" has 2FA enabled with a known secret
        And I want to log in
        When I specify the username as "admin@example.com"
        And I specify the password as "Sylius1!"
        And I log in
        And I submit a valid admin TOTP challenge code trusting this device
        Then I should be fully authenticated as administrator
        When the administrator "admin@example.com" revokes all trusted devices
        And I sign out from the admin panel
        And I want to log in
        And I specify the username as "admin@example.com"
        And I specify the password as "Sylius1!"
        And I log in
        Then I should be on the admin 2FA challenge page
