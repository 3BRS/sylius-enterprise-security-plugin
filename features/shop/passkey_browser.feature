@shop @passkey_browser @javascript
Feature: Registering a passkey through the browser
    In order to know the shipped WebAuthn JavaScript actually runs
    As a developer
    I want the ceremony driven by a real browser against a virtual authenticator

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "passkey-browser@example.com" identified by "Pass1Word!"

    @javascript
    Scenario: The browser ceremony stores a credential the server accepts
        Given the customer "passkey-browser@example.com" can sign in with the password "Pass1Word!"
        And a virtual authenticator is attached to the browser
        And I sign in to the shop in the browser as "passkey-browser@example.com" with password "Pass1Word!"
        When I register a passkey labelled "Virtual Touch ID" in the browser
        Then the browser should list a passkey labelled "Virtual Touch ID"
        And a shop passkey labelled "Virtual Touch ID" should be stored for "passkey-browser@example.com"
