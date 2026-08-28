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
    protected const RECENTLY_USED_MESSAGE = 'This password has been used recently';

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
            $this->session->getPage()->hasContent(self::RECENTLY_USED_MESSAGE),
            'Expected password history validation error not found on page.',
        );
    }

    /**
     * Combination K10: the two rejections must stay distinguishable. Asserting only
     * that the right one appeared would pass on a page that shows both.
     *
     * @Then I should not be notified that this password was recently used
     */
    public function iShouldNotBeNotifiedThatThisPasswordWasRecentlyUsed(): void
    {
        $content = (string) $this->session->getPage()->getContent();

        Assert::notContains(
            $content,
            self::RECENTLY_USED_MESSAGE,
            'The page blames the password history for a rejection that was about the policy.',
        );
    }

    /**
     * @Then I should be notified that the new password is too similar to the current one
     */
    public function iShouldBeNotifiedThatNewPasswordIsTooSimilarToCurrent(): void
    {
        Assert::true(
            $this->session->getPage()->hasContent('New password is too similar to the current password'),
            'Expected similarity validation error not found on page.',
        );
    }
}
