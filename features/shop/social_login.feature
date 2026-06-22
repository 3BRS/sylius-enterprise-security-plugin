@shop @social_login @ui
Feature: Customer social login (OAuth)
    In order to sign in without remembering another password
    As a customer
    I want to sign in with a social provider

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "existing@example.com" identified by "Password1!"

    @ui
    Scenario: First-time social login creates a new account and logs me in
        Given the "google" OAuth provider will return user "g-new-1" with email "new@example.com"
        When I click the "google" social login button
        Then I should be logged in as "new@example.com"
        And the customer "new@example.com" should exist
        And a social link should exist for "new@example.com" with "google" and provider id "g-new-1"

    @ui
    Scenario: Returning user with an existing link logs in without a password
        Given the customer "existing@example.com" is already linked to "google" with id "g-existing-1"
        And the "google" OAuth provider will return user "g-existing-1" with email "existing@example.com"
        When I click the "google" social login button
        Then I should be logged in as "existing@example.com"

    @ui
    Scenario: Social login for an email that matches a local account triggers code confirmation
        Given the "google" OAuth provider will return user "g-new-2" with email "existing@example.com"
        When I click the "google" social login button
        Then I should be on the social link confirm page

    @ui
    Scenario: Confirming with the correct code links the social account
        Given the "google" OAuth provider will return user "g-new-3" with email "existing@example.com"
        When I click the "google" social login button
        And I confirm the social link with the emailed code
        Then I should be logged in as "existing@example.com"
        And a social link should exist for "existing@example.com" with "google" and provider id "g-new-3"

    @ui
    Scenario: Confirming with the wrong code shows an error
        Given the "google" OAuth provider will return user "g-new-4" with email "existing@example.com"
        When I click the "google" social login button
        And I confirm the social link with an incorrect code
        Then I should be on the social link confirm page
        And I should see a social-login error

    @ui
    Scenario: Logged-in customer links a social account from the social accounts page
        Given I am logged in as "existing@example.com"
        And the "google" OAuth provider will return user "g-link-1" with email "existing@example.com"
        When I click the "google" link button on the social accounts page
        Then a social link should exist for "existing@example.com" with "google" and provider id "g-link-1"

    @ui
    Scenario: Customer unlinks a social account from the social accounts page
        Given the customer "existing@example.com" is already linked to "google" with id "g-unlink-1"
        And I am logged in as "existing@example.com"
        When I unlink my "google" social account
        Then the customer "existing@example.com" should not be linked to "google"

    @ui
    Scenario: Customer cannot unlink the last remaining social account when they have no password
        Given the customer "existing@example.com" is already linked to "google" with id "g-last-1"
        And the customer "existing@example.com" has no usable password
        And I am logged in as "existing@example.com"
        When I unlink my "google" social account
        Then the customer "existing@example.com" should still be linked to "google"

    @ui
    Scenario: First-time Microsoft social login creates a new account and logs me in
        Given the "microsoft" OAuth provider will return user "ms-new-1" with email "ms-new@example.com"
        When I click the "microsoft" social login button
        Then I should be logged in as "ms-new@example.com"
        And the customer "ms-new@example.com" should exist
        And a social link should exist for "ms-new@example.com" with "microsoft" and provider id "ms-new-1"

    @ui
    Scenario: Logged-in customer links a Microsoft account from the social accounts page
        Given I am logged in as "existing@example.com"
        And the "microsoft" OAuth provider will return user "ms-link-1" with email "existing@example.com"
        When I click the "microsoft" link button on the social accounts page
        Then a social link should exist for "existing@example.com" with "microsoft" and provider id "ms-link-1"
