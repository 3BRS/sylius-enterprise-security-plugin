<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerMagicLinkTokenRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\MagicLinkTokenGeneratorInterface;
use Webmozart\Assert\Assert;

class MagicLinkContext implements Context
{
    public function __construct(
        private Session $session,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerMagicLinkTokenRepositoryInterface $tokenRepository,
        private MagicLinkTokenGeneratorInterface $tokenGenerator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @When I request a magic link for :email
     */
    public function iRequestAMagicLinkFor(string $email): void
    {
        $this->session->visit('/magic-link');

        $page = $this->session->getPage();
        $input = $page->find('css', '#three_brs_magic_link_request_email');
        Assert::notNull($input, 'Magic link email input not found.');
        $input->setValue($email);

        $submit = $page->find('css', '#three_brs_magic_link_request_submit');
        Assert::notNull($submit, 'Magic link submit button not found.');
        $submit->click();
    }

    /**
     * @Given a valid magic link token :plainToken exists for :email
     */
    public function aValidMagicLinkTokenExistsFor(string $plainToken, string $email): void
    {
        $this->createToken($email, $plainToken, new \DateTimeImmutable('+5 minutes'), null);
    }

    /**
     * @Given an expired magic link token :plainToken exists for :email
     */
    public function anExpiredMagicLinkTokenExistsFor(string $plainToken, string $email): void
    {
        $this->createToken($email, $plainToken, new \DateTimeImmutable('-5 minutes'), null);
    }

    /**
     * @Given a used magic link token :plainToken exists for :email
     */
    public function aUsedMagicLinkTokenExistsFor(string $plainToken, string $email): void
    {
        $this->createToken($email, $plainToken, new \DateTimeImmutable('+5 minutes'), new \DateTimeImmutable('-1 minute'));
    }

    /**
     * @When I follow the magic link :plainToken
     */
    public function iFollowTheMagicLink(string $plainToken): void
    {
        $this->session->visit('/magic-link/verify/' . $plainToken);
    }

    /**
     * @Then I should see a magic link request confirmation
     */
    public function iShouldSeeAMagicLinkRequestConfirmation(): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::contains($url, '/magic-link', sprintf('Expected to stay on magic link page, got "%s".', $url));
    }

    /**
     * @Then a magic link token should have been stored for :email
     */
    public function aMagicLinkTokenShouldHaveBeenStoredFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findShopUser($email);
        $count = $this->tokenRepository->countRecentForShopUser($user, new \DateTimeImmutable('-1 hour'));
        Assert::greaterThan($count, 0, sprintf('No magic link token stored for "%s".', $email));
    }

    /**
     * @Then no magic link token should have been stored for :email
     */
    public function noMagicLinkTokenShouldHaveBeenStoredFor(string $email): void
    {
        $this->entityManager->clear();
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        if ($customer === null) {
            return;
        }
        $user = $customer->getUser();
        if (!$user instanceof ShopUserInterface) {
            return;
        }
        $count = $this->tokenRepository->countRecentForShopUser($user, new \DateTimeImmutable('-1 hour'));
        Assert::same($count, 0, sprintf('Unexpected magic link token stored for "%s".', $email));
    }

    /**
     * @Given :count magic link tokens have recently been issued for :email
     */
    public function magicLinkTokensHaveRecentlyBeenIssuedFor(int $count, string $email): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createToken($email, sprintf('shop-recent-%d', $i), new \DateTimeImmutable('+5 minutes'), null);
        }
    }

    /**
     * @Then exactly :count magic link tokens should exist for :email
     */
    public function exactlyMagicLinkTokensShouldExistFor(int $count, string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findShopUser($email);
        $actual = $this->tokenRepository->countRecentForShopUser($user, new \DateTimeImmutable('-1 hour'));
        Assert::same($actual, $count, sprintf('Expected exactly %d magic link tokens for "%s", got %d.', $count, $email, $actual));
    }

    /**
     * @Then I should see a magic link invalid-or-expired error
     */
    public function iShouldSeeAMagicLinkInvalidOrExpiredError(): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::contains($url, '/magic-link', sprintf('Expected to be on magic link request page, got "%s".', $url));
    }

    /**
     * @Then I should be logged in as :email
     */
    public function iShouldBeLoggedInAs(string $email): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::notContains($url, '/magic-link', sprintf('Expected redirect off magic link pages after login, got "%s".', $url));

        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));
    }

    private function createToken(string $email, string $plainToken, \DateTimeImmutable $expiresAt, ?\DateTimeImmutable $usedAt): void
    {
        $user = $this->findShopUser($email);

        $token = new CustomerMagicLinkToken();
        $token->setShopUser($user);
        $token->setTokenHash($this->tokenGenerator->hash($plainToken));
        $token->setExpiresAt($expiresAt);
        if ($usedAt !== null) {
            $token->setUsedAt($usedAt);
        }

        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    private function findShopUser(string $email): ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));

        $user = $customer->getUser();
        Assert::notNull($user);
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }
}
