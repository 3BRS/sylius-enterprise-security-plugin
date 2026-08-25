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

        // Filling nothing and pressing nothing used to be silent: the step passed and
        // whatever assertion came next failed instead, naming the wrong thing.
        foreach ([
            '[data-test-current-password]' => $currentPassword,
            '[data-test-new-password]' => $newPassword,
            '[data-test-confirmation-new-password]' => $newPassword,
        ] as $selector => $value) {
            $field = $page->find('css', $selector);
            Assert::notNull($field, sprintf('No "%s" on %s.', $selector, $this->session->getCurrentUrl()));
            $field->setValue($value);
        }

        $submit = $page->find('css', '[data-test-button="save-changes"]');
        Assert::notNull($submit, sprintf('No save button on %s.', $this->session->getCurrentUrl()));
        $submit->press();
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
        // Ruling out one message let a password that broke three other rules count as
        // a success: "validpassword" is long enough, so the only error this looked for
        // was never going to be there.
        Assert::false(
            $this->hasAnyPolicyViolation(),
            sprintf('The password was refused on %s.', $this->session->getCurrentUrl()),
        );
    }

    protected function hasAnyPolicyViolation(): bool
    {
        $page = $this->session->getPage();

        foreach ([
            'Password must be at least',
            'must not exceed',
            'uppercase letter',
            'lowercase letter',
            'at least one number',
            'special character',
            'used recently',
            'too similar',
        ] as $message) {
            if ($page->hasContent($message)) {
                return true;
            }
        }

        return false;
    }

    private function assertValidationError(string $message): void
    {
        Assert::true(
            $this->session->getPage()->hasContent($message),
            sprintf('Expected validation error "%s" not found on page.', $message),
        );
    }
}
