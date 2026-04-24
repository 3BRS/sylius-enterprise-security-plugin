<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationShopUserInterface;
use Webmozart\Assert\Assert;

class PasswordExpirationContext implements Context
{
    public function __construct(
        private Session $session,
        private CustomerRepositoryInterface $customerRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the customer :email has an expired password
     */
    public function theCustomerHasAnExpiredPassword(string $email): void
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));

        $shopUser = $customer->getUser();
        Assert::notNull($shopUser, sprintf('Customer "%s" has no user account.', $email));
        Assert::isInstanceOf($shopUser, PasswordExpirationShopUserInterface::class);

        $shopUser->setForcePasswordChange(true);
        $this->entityManager->flush();
    }

    /**
     * @When I try to open the account page
     */
    public function iTryToOpenTheAccountPage(): void
    {
        $this->session->visit('/en_US/account/dashboard');
    }

    /**
     * @Then I should be on the change password page
     */
    public function iShouldBeOnTheChangePasswordPage(): void
    {
        Assert::true(
            str_contains($this->session->getCurrentUrl(), 'change-password'),
            sprintf('Expected to be on the change password page, but current URL is "%s".', $this->session->getCurrentUrl()),
        );
    }

    /**
     * @Then I should not be redirected to the change password page
     */
    public function iShouldNotBeRedirectedToTheChangePasswordPage(): void
    {
        Assert::false(
            str_contains($this->session->getCurrentUrl(), 'change-password'),
            sprintf('Expected NOT to be on the change password page, but current URL is "%s".', $this->session->getCurrentUrl()),
        );
    }
}
