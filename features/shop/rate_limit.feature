@shop @rate_limit @ui
Feature: Customer rate limiting
    In order to protect against brute-force and abusive traffic
    As a store owner
    I want repeated requests to throttled endpoints to be rejected

    Background:
        Given the store operates on a single channel in "United States"
        And the customer login rate limit is set to 2 requests per minute
        And there is a customer account "customer@example.com" identified by "Password1!"

    @ui @T49
    Scenario: Login is rejected after the rate limit is exceeded
        When I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        And I try to sign in with email "customer@example.com" and password "WrongPass1!"
        Then I should see the too-many-requests message

    # The limits come from the test application's configuration, not from a step:
    # customer.password_reset allows 3 requests an hour, customer.magic_link 3 per
    # 15 minutes. Both are keyed on the client address, so the address typed into
    # the form does not spread the budget. RateLimiterCacheHookContext empties the
    # counters before each scenario.
    @ui @T50
    Scenario: Password reset requests are refused once the hourly limit is spent
        When I ask for a password reset for "customer@example.com"
        And I ask for a password reset for "customer@example.com"
        And I ask for a password reset for "customer@example.com"
        Then the request should not have been refused
        When I ask for a password reset for "customer@example.com"
        Then I should see the too-many-requests message

    @ui @T51
    Scenario: Magic link requests are refused once the quarter-hour limit is spent
        When I ask for a magic link for "customer@example.com"
        And I ask for a magic link for "customer@example.com"
        And I ask for a magic link for "customer@example.com"
        Then the request should not have been refused
        When I ask for a magic link for "customer@example.com"
        Then I should see the too-many-requests message
