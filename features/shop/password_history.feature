@shop @password_history @ui
Feature: Customer password history
    In order to keep customer accounts secure
    As a store owner
    I want to prevent customers from reusing recently used passwords

    Background:
        Given the store operates on a single channel in "United States"
        And there is a customer account "john@example.com" identified by "CurrentPass1!"
        And I am logged in as "john@example.com"

    @ui
    Scenario: Customer cannot reuse a password from their history
        Given the password "OldPass1!" is in the history of customer "john@example.com"
        When I want to change my password
        And I change my password from "CurrentPass1!" to "OldPass1!"
        Then I should be notified that this password was recently used

    @ui
    Scenario: Customer can use a new password not in their history
        Given the password "OldPass1!" is in the history of customer "john@example.com"
        When I want to change my password
        And I change my password from "CurrentPass1!" to "BrandNewPass1!"
        Then my password should be changed successfully

    @ui @combination
    Scenario: History is per account — one customer's old password is free for another
        Given the password "SharedPass1!" is in the history of customer "john@example.com"
        # This account is created last on purpose: Sylius' account step swaps the
        # password for a random one and keeps only the newest mapping in shared
        # storage, so the sign-in below has to be the one it describes.
        And there is a customer account "other@example.com" identified by "OtherPass1!"
        And I am logged in as "other@example.com"
        When I want to change my password
        And I change my password from "OtherPass1!" to "SharedPass1!"
        Then my password should be changed successfully

    @ui @combination
    Scenario: A policy rejection does not blame the history
        Given the password "OldPass1!" is in the history of customer "john@example.com"
        When I want to change my password
        And I change my password from "CurrentPass1!" to "sh0rt!"
        Then I should be notified that the new password is too short
        And I should not be notified that this password was recently used

    @ui
    Scenario: Customer cannot change password to one too similar to the current
        When I want to change my password
        And I change my password from "CurrentPass1!" to "CurrentPass1!extra"
        Then I should be notified that the new password is too similar to the current one
