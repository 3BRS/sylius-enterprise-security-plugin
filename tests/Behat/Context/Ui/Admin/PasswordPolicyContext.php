<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

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
     * @When I create an administrator with password :password
     */
    public function iCreateAnAdministratorWithPassword(string $password): void
    {
        $page = $this->session->getPage();
        $page->find('css', '#sylius_admin_admin_user_plainPassword')?->setValue($password);
        $page->pressButton('Create');
    }

    /**
     * @When I change the administrator password to :password
     */
    public function iChangeTheAdministratorPasswordTo(string $password): void
    {
        $page = $this->session->getPage();
        $page->find('css', '#sylius_admin_admin_user_plainPassword')?->setValue($password);
        $page->find('css', '[data-test-update-changes-button]')?->click();
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
     * @Then I should be notified that the password requires a lowercase letter
     */
    public function iShouldBeNotifiedThatThePasswordRequiresALowercaseLetter(): void
    {
        $this->assertValidationError('Password must contain at least one lowercase letter');
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
     * @Then the administrator should be saved successfully
     */
    public function theAdministratorShouldBeSavedSuccessfully(): void
    {
        Assert::false(
            $this->session->getPage()->hasContent('Password must be at least'),
            'Admin save failed due to password policy violation.',
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
