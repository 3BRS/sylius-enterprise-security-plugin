<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Webmozart\Assert\Assert;

class ChangePasswordPolicyContext implements Context
{
    public function __construct(
        private Session $session,
    ) {
    }

    /**
     * @When I change my password from :currentPassword to :newPassword
     */
    public function iChangeMyPasswordFromTo(string $currentPassword, string $newPassword): void
    {
        $page = $this->session->getPage();
        $page->find('css', '[data-test-current-password]')?->setValue($currentPassword);
        $page->find('css', '[data-test-new-password]')?->setValue($newPassword);
        $page->find('css', '[data-test-confirmation-new-password]')?->setValue($newPassword);
        $page->find('css', '[data-test-button="save-changes"]')?->press();
    }

    /**
     * @Then I should be notified that the new password is too short
     */
    public function iShouldBeNotifiedThatTheNewPasswordIsTooShort(): void
    {
        $this->assertValidationError('Password must be at least');
    }

    /**
     * @Then I should be notified that the new password requires an uppercase letter
     */
    public function iShouldBeNotifiedThatTheNewPasswordRequiresAnUppercaseLetter(): void
    {
        $this->assertValidationError('Password must contain at least one uppercase letter');
    }

    /**
     * @Then I should be notified that the new password requires a number
     */
    public function iShouldBeNotifiedThatTheNewPasswordRequiresANumber(): void
    {
        $this->assertValidationError('Password must contain at least one number');
    }

    /**
     * @Then I should be notified that the new password requires a special character
     */
    public function iShouldBeNotifiedThatTheNewPasswordRequiresASpecialCharacter(): void
    {
        $this->assertValidationError('Password must contain at least one special character');
    }

    /**
     * @Then I should be notified that the new password must be at least :limit characters
     */
    public function iShouldBeNotifiedThatTheNewPasswordMustBeAtLeastCharacters(int $limit): void
    {
        $this->assertValidationError(sprintf('at least %d characters', $limit));
    }

    /**
     * @Then the Sylius built-in minimum password length error should not appear
     */
    public function theSyliusBuiltInMinimumPasswordLengthErrorShouldNotAppear(): void
    {
        Assert::false(
            $this->session->getPage()->hasContent('at least 4 characters'),
            'Sylius built-in minimum length error ("at least 4 characters") should be suppressed by our policy error.',
        );
    }

    /**
     * @Then my password should be changed successfully
     */
    public function myPasswordShouldBeChangedSuccessfully(): void
    {
        Assert::false(
            $this->session->getPage()->hasContent('Password must be at least'),
            'Password change failed due to password policy violation.',
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
