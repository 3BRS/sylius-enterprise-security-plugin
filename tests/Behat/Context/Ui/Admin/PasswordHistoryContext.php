<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasswordHistory;
use Webmozart\Assert\Assert;

class PasswordHistoryContext implements Context
{
    public function __construct(
        private Session $session,
        private UserRepositoryInterface $adminUserRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the password :password is in the history of administrator :email
     */
    public function thePasswordIsInTheHistoryOfAdministrator(string $password, string $email): void
    {
        $adminUser = $this->adminUserRepository->findOneByEmail($email);
        Assert::notNull($adminUser, sprintf('Administrator "%s" not found.', $email));

        $entry = new AdminUserPasswordHistory();
        $entry->setAdminUser($adminUser);
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
