@admin @rate_limit @ui
Feature: Admin rate limiting
    In order to protect the admin panel against brute-force and abusive traffic
    As a store owner
    I want repeated requests to throttled admin endpoints to be rejected

    Background:
        Given the store operates on a single channel in "United States"
        And the admin login rate limit is set to 5 requests per minute
        And there is an administrator "admin@example.com" identified by "Password1!"

    @ui
    Scenario: Admin login is rejected after the rate limit is exceeded
        When I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        And I try to sign in to the admin panel with email "admin@example.com" and password "WrongPass1!"
        Then I should see the too-many-requests message
