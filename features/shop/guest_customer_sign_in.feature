@shop @guest_customer_sign_in @ui
Feature: Passwordless sign-in for guest customers and guest carts
    In order to keep what I did before signing in
    As a customer
    I want a passwordless sign-in to take over my guest orders and my guest cart

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt"

    @ui
    Scenario: Signing in with a provider for a guest customer's email creates the account on that customer
        Given a customer "guest@example.com" placed an order "00000022"
        And the "google" OAuth provider will return user "g-guest-1" with verified email "guest@example.com"
        When I click the "google" social login button
        Then I should be logged in as "guest@example.com"
        And the account of "guest@example.com" should keep the order "00000022"

    @ui
    Scenario: A provider that has not verified the email does not take over a guest customer
        Given a customer "guest@example.com" placed an order "00000023"
        And the "google" OAuth provider will return user "g-guest-2" with email "guest@example.com"
        When I click the "google" social login button
        Then I should be told that signing up through the provider is not allowed
        And the guest customer "guest@example.com" should still have no account

    @ui
    Scenario: Signing in with a linked provider takes over the guest cart
        Given there is a customer account "john@example.com" identified by "Password1!"
        And the customer "john@example.com" is already linked to "google" with id "g-cart-1"
        And the "google" OAuth provider will return user "g-cart-1" with email "john@example.com"
        And I have a guest cart with this product
        When I click the "google" social login button
        Then my cart should belong to "john@example.com"

    @ui
    Scenario: Signing in with a passkey takes over the guest cart
        Given there is a customer account "john@example.com" identified by "Password1!"
        And I am logged in to the shop as "john@example.com"
        And I register a shop passkey labelled "Laptop"
        And I sign out of the shop
        And I have a guest cart with this product
        When I sign in to the shop with the passkey "Laptop"
        Then my cart should belong to "john@example.com"
