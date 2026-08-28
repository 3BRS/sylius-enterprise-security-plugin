@shop @passkey @ui
Feature: Customer passkey management
    In order to sign in without typing a password
    As a customer
    I want to manage my registered passkeys

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "existing@example.com" identified by "Password1!"
        And I am logged in as "existing@example.com"

    @ui
    Scenario: Empty passkey list when none registered
        When I visit the passkey management page
        Then I should see no registered passkeys

    @ui
    Scenario: Registered passkey appears in the list
        Given a passkey "credential-1" labelled "MacBook Touch ID" exists for "existing@example.com"
        When I visit the passkey management page
        Then I should see a passkey labelled "MacBook Touch ID"

    @ui
    Scenario: Multiple passkeys are listed
        Given a passkey "credential-1" labelled "MacBook Touch ID" exists for "existing@example.com"
        And a passkey "credential-2" labelled "YubiKey" exists for "existing@example.com"
        When I visit the passkey management page
        Then I should see a passkey labelled "MacBook Touch ID"
        And I should see a passkey labelled "YubiKey"

    @ui @combination
    Scenario: One customer can neither see nor remove another customer's passkey
        Given there is a customer account "other@example.com" identified by "Password1!"
        And a passkey "credential-other" labelled "Somebody Elses Key" exists for "other@example.com"
        And a passkey "credential-mine" labelled "My Own Key" exists for "existing@example.com"
        When I visit the passkey management page
        Then I should see a passkey labelled "My Own Key"
        And I should not see a passkey labelled "Somebody Elses Key"
        When I try to remove the passkey "credential-other" that belongs to somebody else
        Then the request should have been refused as not found
        And the passkey "credential-other" should still exist

    @ui
    Scenario: Removing a passkey when another sign-in method exists
        Given a passkey "credential-1" labelled "MacBook Touch ID" exists for "existing@example.com"
        When I visit the passkey management page
        And I remove the passkey "credential-1"
        Then I should see no registered passkeys
