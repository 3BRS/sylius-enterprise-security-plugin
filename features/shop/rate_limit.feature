@shop @rate_limit @ui
Feature: Customer rate limiting
    In order to protect against brute-force and abusive traffic
    As a store owner
    I want repeated requests to throttled endpoints to be rejected

    Background:
        Given the store operates on a single channel in "United States"
        And the customer login rate limit is set to 2 requests per minute
        And there is a customer account "customer@example.com" identified by "Password1!"

    @ui
    Scenario: Login is rejected after the rate limit is exceeded
        When I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        Then I should see the too-many-requests message
