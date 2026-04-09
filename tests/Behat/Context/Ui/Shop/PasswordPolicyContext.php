<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Webmozart\Assert\Assert;

class PasswordPolicyContext implements Context
{
    public function __construct(
        private Session $session,
    ) {
    }

    /**
     * @Given the store requires uppercase letters in customer passwords
     * @Given the store requires lowercase letters in customer passwords
     * @Given the store requires numbers in customer passwords
     * @Given the store requires special characters in customer passwords
     */
    public function theStoreRequiresPasswordPolicy(): void
    {
        // Policy is pre-configured in test app config — this step is documentation only
    }

    /**
     * @When I register with password :password
     */
    public function iRegisterWithPassword(string $password): void
    {
        $page = $this->session->getPage();
        $page->find('css', '[data-test-password-first]')?->setValue($password);
        $page->find('css', '[data-test-password-second]')?->setValue($password);
        $page->find('css', '[data-test-button="register-button"]')?->press();
    }

    /**
     * @Then I should be notified that the password is too short
     */
    public function iShouldBeNotifiedThatThePasswordIsTooShort(): void
    {
        $this->assertValidationError('Password must be at least');
    }

    /**
     * @Then I should be notified that the password requires an uppercase letter
     */
    public function iShouldBeNotifiedThatThePasswordRequiresAnUppercaseLetter(): void
    {
        $this->assertValidationError('Password must contain at least one uppercase letter');
    }

    /**
     * @Then I should be notified that the password requires a number
     */
    public function iShouldBeNotifiedThatThePasswordRequiresANumber(): void
    {
        $this->assertValidationError('Password must contain at least one number');
    }

    /**
     * @Then I should be notified that the password requires a special character
     */
    public function iShouldBeNotifiedThatThePasswordRequiresASpecialCharacter(): void
    {
        $this->assertValidationError('Password must contain at least one special character');
    }

    /**
     * @Then I should be registered successfully
     */
    public function iShouldBeRegisteredSuccessfully(): void
    {
        Assert::false(
            $this->session->getPage()->hasContent('Password must be at least'),
            'Registration failed due to password policy violation.',
        );
    }

    private function assertValidationError(string $message): void
    {
        Assert::true(
            $this->session->getPage()->hasContent($message),
            sprintf('Expected validation error "%s" not found on page.', $message),
        );
    }
}
