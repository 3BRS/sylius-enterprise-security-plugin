<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasswordHistory;
use Webmozart\Assert\Assert;

class PasswordHistoryContext implements Context
{
    public function __construct(
        private Session $session,
        private CustomerRepositoryInterface $customerRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the password :password is in the history of customer :email
     */
    public function thePasswordIsInTheHistoryOfCustomer(string $password, string $email): void
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));

        $shopUser = $customer->getUser();
        Assert::notNull($shopUser, sprintf('Customer "%s" has no user account.', $email));

        $entry = new CustomerPasswordHistory();
        $entry->setShopUser($shopUser);
        $entry->setPasswordHash(password_hash($password, \PASSWORD_BCRYPT));

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    /**
     * @Then I should be notified that this password was recently used
     */
    public function iShouldBeNotifiedThatThisPasswordWasRecentlyUsed(): void
    {
        Assert::true(
            $this->session->getPage()->hasContent('This password has been used recently'),
            'Expected password history validation error not found on page.',
        );
    }
}
