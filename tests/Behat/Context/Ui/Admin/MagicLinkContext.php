<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\SpySender;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use Webmozart\Assert\Assert;

class MagicLinkContext implements Context
{
    public function __construct(
        protected Session $session,
        protected UserRepositoryInterface $adminUserRepository,
        protected MagicLinkTokenGeneratorInterface $tokenGenerator,
        protected EntityManagerInterface $entityManager,
        protected SpySender $spySender,
        protected SettingsWriterInterface $settingsWriter,
        protected SettingsProviderInterface $settingsProvider,
    ) {
    }

    #[BeforeScenario]
    public function resetSentEmails(): void
    {
        $this->spySender->reset();
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
        $count = $this->countTokensFor($user);
        Assert::greaterThan($count, 0, sprintf('No admin magic link token stored for "%s".', $email));
    }

    /**
     * @Then no admin magic link token should have been stored for :email
     */
    public function noAdminMagicLinkTokenShouldHaveBeenStoredFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        if (!$user instanceof AdminUserInterface) {
            return;
        }
        $count = $this->countTokensFor($user);
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
        $actual = $this->countTokensFor($user);
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

        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($user, sprintf('Administrator "%s" not found.', $email));

        // Leaving the magic-link page says only that something redirected, and the
        // row above was created by the Background — neither shows a session was
        // opened. The dashboard is behind the firewall, so reaching it without
        // being bounced to the sign-in page is what "signed in" means.
        $this->session->visit('/admin/');

        $dashboardUrl = $this->session->getCurrentUrl();
        Assert::notContains(
            $dashboardUrl,
            '/admin/login',
            sprintf('Following the magic link left no session — the dashboard bounced to "%s".', $dashboardUrl),
        );
        Assert::same(200, $this->session->getStatusCode(), 'The admin dashboard was not reachable after the magic link.');
    }

    /**
     * @Then an admin magic link email should have been sent to :email
     */
    public function anAdminMagicLinkEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::true(
            $this->spySender->hasSentEmail(Emails::MAGIC_LINK, $email),
            sprintf('No magic link email was sent to "%s" (sent: %s).', $email, $this->spySender->describeSentEmails()),
        );
    }

    /**
     * @Then no admin magic link email should have been sent to :email
     */
    public function noAdminMagicLinkEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::false(
            $this->spySender->hasSentEmail(Emails::MAGIC_LINK, $email),
            sprintf('A magic link email was sent to "%s" although none was expected.', $email),
        );
    }

    /**
     * Storing a token and mailing a usable link are different things: the token
     * assertions above still pass when the address in the email points at the
     * shop route or carries the stored hash instead of the plain token.
     *
     * @When I follow the admin magic link from the email sent to :email
     */
    public function iFollowTheAdminMagicLinkFromTheEmailSentTo(string $email): void
    {
        $data = $this->spySender->getLastSentDataTo(Emails::MAGIC_LINK, $email);
        Assert::notNull($data, sprintf('No magic link email was sent to "%s" (sent: %s).', $email, $this->spySender->describeSentEmails()));
        Assert::keyExists($data, 'magicLinkUrl', 'The magic link email carries no sign-in address.');

        $this->session->visit((string) $data['magicLinkUrl']);
    }

    /**
     * The guard that refuses the request runs above the controller, so a refusal
     * must leave the link exactly as it was — an administrator turned away by an
     * address restriction still has to be able to use the link from an allowed
     * address. Asserting the response code alone does not show that: Mink follows
     * redirects, so a consumed link that signs the administrator in and then hits
     * the same restriction on the dashboard reports the same 403.
     *
     * @Then the admin magic link :plainToken should still be unused
     */
    public function theAdminMagicLinkShouldStillBeUnused(string $plainToken): void
    {
        $this->entityManager->clear();

        $token = $this->entityManager->getRepository(AdminUserMagicLinkToken::class)
            ->findOneBy(['tokenHash' => $this->tokenGenerator->hash($plainToken)]);

        Assert::notNull($token, sprintf('Magic link token "%s" no longer exists.', $plainToken));
        Assert::null($token->getUsedAt(), sprintf('Magic link token "%s" was consumed by a refused request.', $plainToken));
    }

    /**
     * @Given magic link is disabled for customers
     */
    public function magicLinkIsDisabledForCustomers(): void
    {
        $this->switchMagicLink(SettingsScope::CUSTOMER, false);
    }

    /**
     * Combination K17: a switch thrown for one scope must not answer for the other.
     * Finding the customer page gone proves only that the switch works at all — the
     * admin page still being there is what proves it was scoped.
     *
     * @Then the customer magic link page should be gone
     */
    public function theCustomerMagicLinkPageShouldBeGone(): void
    {
        $this->session->visit('/magic-link');

        Assert::same(404, $this->session->getStatusCode(), sprintf(
            'The customer magic link page answered %d after the customer switch was turned off.',
            $this->session->getStatusCode(),
        ));
    }

    /**
     * @Then the admin magic link page should still be there
     */
    public function theAdminMagicLinkPageShouldStillBeThere(): void
    {
        $this->session->visit('/admin/magic-link');

        Assert::same(200, $this->session->getStatusCode(), sprintf(
            'The admin magic link page answered %d although only the customer switch was turned off.',
            $this->session->getStatusCode(),
        ));
    }

    protected function switchMagicLink(SettingsScope $scope, bool $enabled): void
    {
        $this->settingsWriter->set('magic_link.enabled', $scope, $enabled);
        $this->settingsWriter->flush();
        $this->settingsProvider->refresh();
    }

    protected function createToken(string $email, string $plainToken, \DateTimeImmutable $expiresAt, ?\DateTimeImmutable $usedAt): void
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

    protected function findAdminUser(string $email): AdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($user, sprintf('Administrator "%s" not found.', $email));
        Assert::isInstanceOf($user, AdminUserInterface::class);

        return $user;
    }

    protected function countTokensFor(AdminUserInterface $user): int
    {
        return $this->entityManager->getRepository(AdminUserMagicLinkToken::class)->count(['adminUser' => $user]);
    }
}
