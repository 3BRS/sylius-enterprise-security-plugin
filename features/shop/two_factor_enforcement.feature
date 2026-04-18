@shop @two_factor_enforcement @ui
Feature: Customer two-factor authentication enforcement
    In order to protect all customer accounts
    As a store owner
    I want to force customers to enable 2FA before using the shop

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "Password1!"
        And 2FA enforcement is enabled for customers

    @ui
    Scenario: Customer without 2FA is redirected to the setup page
        Given I am logged in as "john@example.com"
        When I visit the account dashboard
        Then I should be redirected to the 2FA setup page

    @ui
    Scenario: Customer with 2FA enabled is not redirected
        Given the customer "john@example.com" already has 2FA enabled
        And I am logged in as "john@example.com"
        When I visit the account dashboard
        Then I should remain on the account dashboard
