<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserMagicLinkTokenRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\MagicLinkTokenGeneratorInterface;
use Webmozart\Assert\Assert;

class MagicLinkContext implements Context
{
    public function __construct(
        private Session $session,
        private UserRepositoryInterface $adminUserRepository,
        private AdminUserMagicLinkTokenRepositoryInterface $tokenRepository,
        private MagicLinkTokenGeneratorInterface $tokenGenerator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @When I request an admin magic link for :email
     */
    public function iRequestAnAdminMagicLinkFor(string $email): void
    {
        $this->session->visit('/admin/magic-link');

        $page = $this->session->getPage();
        $input = $page->find('css', '#three_brs_magic_link_request_email');
        Assert::notNull($input, 'Admin magic link email input not found.');
        $input->setValue($email);

        $submit = $page->find('css', '#three_brs_admin_magic_link_request_submit');
        Assert::notNull($submit, 'Admin magic link submit button not found.');
        $submit->click();
    }

    /**
     * @Given a valid admin magic link token :plainToken exists for :email
     */
    public function aValidAdminMagicLinkTokenExistsFor(string $plainToken, string $email): void
    {
        $this->createToken($email, $plainToken, new \DateTimeImmutable('+5 minutes'), null);
    }

    /**
     * @Given an expired admin magic link token :plainToken exists for :email
     */
    public function anExpiredAdminMagicLinkTokenExistsFor(string $plainToken, string $email): void
    {
        $this->createToken($email, $plainToken, new \DateTimeImmutable('-5 minutes'), null);
    }

    /**
     * @Given a used admin magic link token :plainToken exists for :email
     */
    public function aUsedAdminMagicLinkTokenExistsFor(string $plainToken, string $email): void
    {
        $this->createToken($email, $plainToken, new \DateTimeImmutable('+5 minutes'), new \DateTimeImmutable('-1 minute'));
    }

    /**
     * @When I follow the admin magic link :plainToken
     */
    public function iFollowTheAdminMagicLink(string $plainToken): void
    {
        $this->session->visit('/admin/magic-link/verify/' . $plainToken);
    }

    /**
     * @Then I should see an admin magic link request confirmation
     */
    public function iShouldSeeAnAdminMagicLinkRequestConfirmation(): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::contains($url, '/admin/magic-link', sprintf('Expected to stay on admin magic link page, got "%s".', $url));
    }

    /**
     * @Then an admin magic link token should have been stored for :email
     */
    public function anAdminMagicLinkTokenShouldHaveBeenStoredFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findAdminUser($email);
        $count = $this->tokenRepository->countRecentForAdminUser($user, new \DateTimeImmutable('-1 hour'));
        Assert::greaterThan($count, 0, sprintf('No admin magic link token stored for "%s".', $email));
    }

    /**
     * @Then no admin magic link token should have been stored for :email
     */
    public function noAdminMagicLinkTokenShouldHaveBeenStoredFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->adminUserRepository->findOneBy(['email' => $email]);
        if (!$user instanceof AdminUserInterface) {
            return;
        }
        $count = $this->tokenRepository->countRecentForAdminUser($user, new \DateTimeImmutable('-1 hour'));
        Assert::same($count, 0, sprintf('Unexpected admin magic link token stored for "%s".', $email));
    }

    /**
     * @Given :count admin magic link tokens have recently been issued for :email
     */
    public function adminMagicLinkTokensHaveRecentlyBeenIssuedFor(int $count, string $email): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createToken($email, sprintf('admin-recent-%d', $i), new \DateTimeImmutable('+5 minutes'), null);
        }
    }

    /**
     * @Then exactly :count admin magic link tokens should exist for :email
     */
    public function exactlyAdminMagicLinkTokensShouldExistFor(int $count, string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findAdminUser($email);
        $actual = $this->tokenRepository->countRecentForAdminUser($user, new \DateTimeImmutable('-1 hour'));
        Assert::same($actual, $count, sprintf('Expected exactly %d admin magic link tokens for "%s", got %d.', $count, $email, $actual));
    }

    /**
     * @Then I should see an admin magic link invalid-or-expired error
     */
    public function iShouldSeeAnAdminMagicLinkInvalidOrExpiredError(): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::contains($url, '/admin/magic-link', sprintf('Expected to be on admin magic link request page, got "%s".', $url));
    }

    /**
     * @Then I should be logged in as admin :email
     */
    public function iShouldBeLoggedInAsAdmin(string $email): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::notContains($url, '/admin/magic-link', sprintf('Expected redirect off admin magic link pages after login, got "%s".', $url));

        $user = $this->adminUserRepository->findOneBy(['email' => $email]);
        Assert::notNull($user, sprintf('Administrator "%s" not found.', $email));
    }

    private function createToken(string $email, string $plainToken, \DateTimeImmutable $expiresAt, ?\DateTimeImmutable $usedAt): void
    {
        $user = $this->findAdminUser($email);

        $token = new AdminUserMagicLinkToken();
        $token->setAdminUser($user);
        $token->setTokenHash($this->tokenGenerator->hash($plainToken));
        $token->setExpiresAt($expiresAt);
        if ($usedAt !== null) {
            $token->setUsedAt($usedAt);
        }

        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    private function findAdminUser(string $email): AdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['email' => $email]);
        Assert::notNull($user, sprintf('Administrator "%s" not found.', $email));
        Assert::isInstanceOf($user, AdminUserInterface::class);

        return $user;
    }
}
