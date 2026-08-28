@admin @social_login @ui
Feature: Admin social login (OAuth)
    In order to sign in to the admin panel without another password
    As an administrator
    I want to sign in with a social provider

    Background:
        Given the store operates on a single channel in "United States"
        And there is an administrator "admin@example.com" identified by "AdminPass1!"

    @ui
    Scenario: First-time admin social login auto-registers an admin account
        Given the "google" OAuth provider will return admin user "g-admin-1" with email "newadmin@example.com"
        When I click the admin "google" social login button
        Then I should be logged in as admin "newadmin@example.com"
        And the admin "newadmin@example.com" should exist
        And an admin social link should exist for "newadmin@example.com" with "google" and provider id "g-admin-1"

    @ui
    Scenario: Returning admin with an existing link logs in without a password
        Given the admin "admin@example.com" is already linked to "google" with id "g-admin-existing"
        And the "google" OAuth provider will return admin user "g-admin-existing" with email "admin@example.com"
        When I click the admin "google" social login button
        Then I should be logged in as admin "admin@example.com"

    @ui
    Scenario: Admin with matching email must confirm a code before linking
        Given the "google" OAuth provider will return admin user "g-admin-new-2" with email "admin@example.com"
        When I click the admin "google" social login button
        Then I should be on the admin social link confirm page
        And an admin account linking code email should have been sent to "admin@example.com"

    @ui
    Scenario: Admin confirms with the correct code to create the link
        Given the "google" OAuth provider will return admin user "g-admin-new-3" with email "admin@example.com"
        When I click the admin "google" social login button
        And I confirm the admin social link with the emailed code
        Then I should be logged in as admin "admin@example.com"
        And an admin social link should exist for "admin@example.com" with "google" and provider id "g-admin-new-3"

    @ui
    Scenario: Admin confirming with the wrong code sees an error
        Given the "google" OAuth provider will return admin user "g-admin-new-4" with email "admin@example.com"
        When I click the admin "google" social login button
        And I confirm the admin social link with an incorrect code
        Then I should be on the admin social link confirm page
        And I should see an admin social-login error

    @ui
    Scenario: Administrator links a social account from the social accounts page
        Given I am logged in as "admin@example.com" administrator
        And the "google" OAuth provider will return admin user "g-admin-link-1" with email "admin@example.com"
        When I click the admin "google" link button on the social accounts page
        Then an admin social link should exist for "admin@example.com" with "google" and provider id "g-admin-link-1"

    @ui
    Scenario: Administrator unlinks a social account from the social accounts page
        Given the admin "admin@example.com" is already linked to "google" with id "g-admin-unlink-1"
        And I am logged in as "admin@example.com" administrator
        When I unlink my admin "google" social account
        Then the administrator "admin@example.com" should not be linked to "google"

    @ui
    Scenario: Administrator cannot unlink the last remaining social account when they have no password
        Given the admin "admin@example.com" is already linked to "google" with id "g-admin-last-1"
        And the administrator "admin@example.com" has no usable password
        And I am logged in as "admin@example.com" administrator
        When I unlink my admin "google" social account
        Then the administrator "admin@example.com" should still be linked to "google"

    @ui
    Scenario: First-time admin Microsoft social login auto-registers an admin account
        Given the "microsoft" OAuth provider will return admin user "ms-admin-1" with email "newadmin-ms@example.com"
        When I click the admin "microsoft" social login button
        Then I should be logged in as admin "newadmin-ms@example.com"
        And the admin "newadmin-ms@example.com" should exist
        And an admin social link should exist for "newadmin-ms@example.com" with "microsoft" and provider id "ms-admin-1"
