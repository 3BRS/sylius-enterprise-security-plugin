<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Webmozart\Assert\Assert;

class CustomerPasswordPolicyContext implements Context
{
    public function __construct(
        private Session $session,
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    /**
     * @When I change the password of customer :email to :password
     */
    public function iChangeThePasswordOfCustomerTo(string $email, string $password): void
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::notNull($customer, sprintf('Customer with email "%s" not found.', $email));

        $this->session->visit(sprintf('/admin/customers/%d/edit', $customer->getId()));

        $page = $this->session->getPage();
        $page->find('css', '[data-test-password]')?->setValue($password);
        $page->find('css', '[data-test-update-changes-button]')?->click();
    }

    /**
     * @Then I should be notified that the customer password is too short
     */
    public function iShouldBeNotifiedThatTheCustomerPasswordIsTooShort(): void
    {
        $this->assertValidationError('Password must be at least');
    }

    /**
     * @Then I should be notified that the customer password requires an uppercase letter
     */
    public function iShouldBeNotifiedThatTheCustomerPasswordRequiresAnUppercaseLetter(): void
    {
        $this->assertValidationError('Password must contain at least one uppercase letter');
    }

    /**
     * @Then I should be notified that the customer password requires a number
     */
    public function iShouldBeNotifiedThatTheCustomerPasswordRequiresANumber(): void
    {
        $this->assertValidationError('Password must contain at least one number');
    }

    /**
     * @Then I should be notified that the customer password requires a special character
     */
    public function iShouldBeNotifiedThatTheCustomerPasswordRequiresASpecialCharacter(): void
    {
        $this->assertValidationError('Password must contain at least one special character');
    }

    /**
     * @Then the customer should be saved successfully
     */
    public function theCustomerShouldBeSavedSuccessfully(): void
    {
        Assert::false(
            $this->session->getPage()->hasContent('Password must be at least'),
            'Customer save failed due to password policy violation.',
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
