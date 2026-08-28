@shop @passkey_ceremony @ui
Feature: Customer passkey registration and login ceremony
    In order to verify the server-side WebAuthn ceremony works end to end
    As a developer
    I want a real passkey to be registered and used to sign in, with all crypto verified by the server

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "passkey-user@example.com" identified by "Pass1Word!"

    @ui
    Scenario: Registering a passkey persists a credential for the user
        Given I am logged in to the shop as "passkey-user@example.com"
        When I register a shop passkey labelled "MacBook Touch ID"
        Then a shop passkey labelled "MacBook Touch ID" should be stored for "passkey-user@example.com"

    @ui
    Scenario: Signing in with a previously registered passkey succeeds
        Given I am logged in to the shop as "passkey-user@example.com"
        And I register a shop passkey labelled "iPhone"
        And I sign out of the shop
        When I sign in to the shop with the passkey "iPhone"
        Then I should be logged in to the shop as "passkey-user@example.com"

    @ui @combination
    Scenario: A passkey does not ask a customer with 2FA for a second factor
        Given the customer "passkey-user@example.com" has 2FA enabled with a known secret
        And I am logged in to the shop as "passkey-user@example.com"
        And I register a shop passkey labelled "Second Factor Free"
        And I sign out of the shop
        When I sign in to the shop with the passkey "Second Factor Free"
        Then the passkey sign-in should have skipped the second factor for "passkey-user@example.com"

    @ui
    Scenario: Signing in with an unknown passkey is rejected
        When I attempt to sign in to the shop with an unknown passkey
        Then the last passkey login should have failed
