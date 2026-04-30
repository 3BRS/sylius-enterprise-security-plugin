@admin @passkey_ceremony @ui
Feature: Admin passkey registration and login ceremony
    In order to verify the server-side WebAuthn ceremony works end to end for admins
    As a developer
    I want a real passkey to be registered and used to sign in, with all crypto verified by the server

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "passkey-admin@example.com" identified by "AdminPass1!"

    @ui
    Scenario: Registering an admin passkey persists a credential for the admin
        Given I am logged in to the admin as "passkey-admin@example.com"
        When I register an admin passkey labelled "Yubikey 5"
        Then an admin passkey labelled "Yubikey 5" should be stored for "passkey-admin@example.com"

    @ui
    Scenario: Signing in with a previously registered admin passkey succeeds
        Given I am logged in to the admin as "passkey-admin@example.com"
        And I register an admin passkey labelled "Office laptop"
        And I sign out of the admin
        When I sign in to the admin with the passkey "Office laptop"
        Then I should be logged in to the admin as "passkey-admin@example.com"

    @ui
    Scenario: Signing in with an unknown passkey is rejected on admin
        When I attempt to sign in to the admin with an unknown passkey
        Then the last admin passkey login should have failed
