<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\SpySender;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenGeneratorInterface;
use Webmozart\Assert\Assert;

class MagicLinkContext implements Context
{
    public function __construct(
        protected Session $session,
        protected CustomerRepositoryInterface $customerRepository,
        protected MagicLinkTokenGeneratorInterface $tokenGenerator,
        protected EntityManagerInterface $entityManager,
        protected SpySender $spySender,
    ) {
    }

    #[BeforeScenario]
    public function resetSentEmails(): void
    {
        $this->spySender->reset();
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
        $count = $this->countTokensFor($user);
        Assert::greaterThan($count, 0, sprintf('No magic link token stored for "%s".', $email));
    }

    /**
     * @Then no magic link token should have been stored for :email
     */
    public function noMagicLinkTokenShouldHaveBeenStoredFor(string $email): void
    {
        $this->entityManager->clear();
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        if ($customer === null) {
            return;
        }
        $user = $customer->getUser();
        if (!$user instanceof ShopUserInterface) {
            return;
        }
        $count = $this->countTokensFor($user);
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
        $actual = $this->countTokensFor($user);
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

        // Being off the magic-link pages says only that something redirected, and the
        // customer row was created by the Given two lines above — neither shows a
        // session was opened. The account dashboard is behind the firewall, so
        // reaching it without landing on the sign-in page is what "signed in" means.
        $this->session->visit('/en_US/account/dashboard');

        $dashboardUrl = $this->session->getCurrentUrl();
        Assert::notContains(
            $dashboardUrl,
            '/login',
            sprintf('Following the magic link left no session — the dashboard bounced to "%s".', $dashboardUrl),
        );
        Assert::same(200, $this->session->getStatusCode(), 'The account dashboard was not reachable after the magic link.');

        Assert::true(
            $this->session->getPage()->hasContent($email),
            sprintf('The dashboard does not show "%s", so the session belongs to somebody else.', $email),
        );
    }

    /**
     * @Then a magic link email should have been sent to :email
     */
    public function aMagicLinkEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::true(
            $this->spySender->hasSentEmail(Emails::MAGIC_LINK, $email),
            sprintf('No magic link email was sent to "%s" (sent: %s).', $email, $this->spySender->describeSentEmails()),
        );
    }

    /**
     * @Then no magic link email should have been sent to :email
     */
    public function noMagicLinkEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::false(
            $this->spySender->hasSentEmail(Emails::MAGIC_LINK, $email),
            sprintf('A magic link email was sent to "%s" although none was expected.', $email),
        );
    }

    /**
     * Storing a token and mailing a usable link are different things: the token
     * assertions above still pass when the address in the email points at the
     * wrong route or carries the stored hash instead of the plain token.
     *
     * @When I follow the magic link from the email sent to :email
     */
    public function iFollowTheMagicLinkFromTheEmailSentTo(string $email): void
    {
        $data = $this->spySender->getLastSentDataTo(Emails::MAGIC_LINK, $email);
        Assert::notNull($data, sprintf('No magic link email was sent to "%s" (sent: %s).', $email, $this->spySender->describeSentEmails()));
        Assert::keyExists($data, 'magicLinkUrl', 'The magic link email carries no sign-in address.');

        $this->session->visit((string) $data['magicLinkUrl']);
    }

    /**
     * @Then the magic link email to :email should expire in :minutes minutes
     */
    public function theMagicLinkEmailToShouldExpireInMinutes(string $email, int $minutes): void
    {
        $data = $this->spySender->getLastSentDataTo(Emails::MAGIC_LINK, $email);
        Assert::notNull($data, sprintf('No magic link email was sent to "%s".', $email));
        Assert::keyExists($data, 'expirationMinutes', 'The magic link email states no expiration.');
        Assert::same((int) $data['expirationMinutes'], $minutes, sprintf(
            'The magic link email announces %d minutes, the configured lifetime is %d.',
            (int) $data['expirationMinutes'],
            $minutes,
        ));
    }

    protected function createToken(string $email, string $plainToken, \DateTimeImmutable $expiresAt, ?\DateTimeImmutable $usedAt): void
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

    protected function findShopUser(string $email): ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));

        $user = $customer->getUser();
        Assert::notNull($user);
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }

    protected function countTokensFor(ShopUserInterface $user): int
    {
        return $this->entityManager->getRepository(CustomerMagicLinkToken::class)->count(['shopUser' => $user]);
    }
}
