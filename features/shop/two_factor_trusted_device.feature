@shop @two_factor_trusted_device @ui
Feature: Customer two-factor authentication trusted-device skip
    In order to avoid entering a TOTP code on every login from the same browser
    As a customer with 2FA enabled
    I want to mark a browser as trusted during the 2FA challenge

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "Password1!"

    @ui
    Scenario: Customer marks device as trusted and skips 2FA on subsequent login
        Given the customer "john@example.com" has 2FA enabled with a known secret
        When I sign in with email "john@example.com" and password "Password1!"
        And I submit a valid TOTP challenge code trusting this device
        Then I should be fully authenticated
        When I sign out from the shop
        And I sign in with email "john@example.com" and password "Password1!"
        Then I should be fully authenticated

    @ui
    Scenario: Trusted device cookie is revoked after bumping the user's token version
        Given the customer "john@example.com" has 2FA enabled with a known secret
        When I sign in with email "john@example.com" and password "Password1!"
        And I submit a valid TOTP challenge code trusting this device
        Then I should be fully authenticated
        When the customer "john@example.com" revokes all trusted devices
        And I sign out from the shop
        And I sign in with email "john@example.com" and password "Password1!"
        Then I should be on the 2FA challenge page
