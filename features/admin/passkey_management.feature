@admin @passkey @ui
Feature: Admin passkey management
    In order to sign in without typing a password
    As an administrator
    I want to manage my registered passkeys

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "Password1!"
        And I am logged in as "admin@example.com" administrator

    @ui
    Scenario: Empty passkey list when none registered
        When I visit the admin passkey management page
        Then I should see no registered admin passkeys

    @ui
    Scenario: Registered passkey appears in the list
        Given an admin passkey "credential-1" labelled "YubiKey" exists for "admin@example.com"
        When I visit the admin passkey management page
        Then I should see an admin passkey labelled "YubiKey"

    @ui
    Scenario: Multiple passkeys are listed
        Given an admin passkey "credential-1" labelled "YubiKey" exists for "admin@example.com"
        And an admin passkey "credential-2" labelled "Backup Key" exists for "admin@example.com"
        When I visit the admin passkey management page
        Then I should see an admin passkey labelled "YubiKey"
        And I should see an admin passkey labelled "Backup Key"

    @ui
    Scenario: Removing a passkey when another sign-in method exists
        Given an admin passkey "credential-1" labelled "YubiKey" exists for "admin@example.com"
        When I visit the admin passkey management page
        And I remove the admin passkey "credential-1"
        Then I should see no registered admin passkeys
