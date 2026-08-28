<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\ShopUserLockoutManagerInterface;
use Webmozart\Assert\Assert;

/**
 * Assertions for the combinations in §6 of docs/manual-test-plan.md, where a
 * passwordless way in meets a guard that only stands in front of the password
 * form. Each of them is a deliberate design decision the plugin documents, and
 * none of them is visible from a single-feature scenario.
 */
class AlternativeSignInContext implements Context
{
    protected const CHALLENGE_MARKER = 'data-test-two-factor-challenge';

    /**
     * Failures to feed the lockout manager before giving up. The configured
     * threshold is five; anything past that means the policy is off and the
     * scenario would be testing nothing.
     */
    protected const LOCKOUT_ATTEMPT_CEILING = 20;

    public function __construct(
        protected Session $session,
        protected AbstractBrowser $client,
        protected UrlGeneratorInterface $router,
        protected CustomerRepositoryInterface $customerRepository,
        protected ShopUserLockoutManagerInterface $lockoutManager,
    ) {
    }

    /**
     * Locks the account the way a wrong password does, rather than by writing
     * the columns by hand — a lockout the production manager did not produce is
     * not evidence about what the production manager guards.
     *
     * The wording differs from the lockout suite's own step on purpose: this
     * context lives in suites that already load Sylius' shop login context, and
     * Behat refuses a suite in which one step text is defined twice.
     *
     * @Given the customer :email has been locked out
     */
    public function theCustomerHasBeenLockedOut(string $email): void
    {
        $user = $this->findShopUser($email);

        for ($attempt = 0; $attempt < self::LOCKOUT_ATTEMPT_CEILING; ++$attempt) {
            if ($this->lockoutManager->isLocked($user)) {
                return;
            }

            $this->lockoutManager->recordFailure($user);
        }

        Assert::true($this->lockoutManager->isLocked($user), sprintf(
            'Customer "%s" is still not locked after %d failures — is account lockout switched off?',
            $email,
            self::LOCKOUT_ATTEMPT_CEILING,
        ));
    }

    /**
     * Reaching the dashboard is not enough on its own: with a second factor
     * pending, scheb holds a two-factor token that is not anonymous either, and
     * the redirect goes to the challenge rather than to the sign-in page. This
     * asks for the dashboard and insists on getting the dashboard.
     *
     * @Then I should be signed in without a second factor as :email
     */
    public function iShouldBeSignedInWithoutASecondFactorAs(string $email): void
    {
        $this->session->visit($this->router->generate('sylius_shop_account_dashboard', ['_locale' => 'en_US']));

        $this->assertLandedOnTheDashboard($this->session->getCurrentUrl(), $this->session->getPage()->getContent(), $email);
        Assert::same(200, $this->session->getStatusCode(), 'The account dashboard was not reachable.');
    }

    /**
     * The passkey ceremony talks to the JSON endpoints through the BrowserKit
     * client rather than through Mink, so the session it opened is only visible
     * on that client.
     *
     * @Then the passkey sign-in should have skipped the second factor for :email
     */
    public function thePasskeySignInShouldHaveSkippedTheSecondFactorFor(string $email): void
    {
        // The client does not follow redirects by default, and the dashboard is
        // exactly where the interesting redirect would happen — reading the first
        // response would judge the sign-in by an empty 302 body.
        $following = $this->client->isFollowingRedirects();
        $this->client->followRedirects(true);

        try {
            $this->client->request('GET', $this->router->generate('sylius_shop_account_dashboard', ['_locale' => 'en_US']));
        } finally {
            $this->client->followRedirects($following);
        }

        $this->assertLandedOnTheDashboard(
            (string) $this->client->getRequest()->getUri(),
            $this->client->getInternalResponse()->getContent(),
            $email,
        );
    }

    protected function findShopUser(string $email): ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));

        $user = $customer->getUser();
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }

    protected function assertLandedOnTheDashboard(string $url, string $content, string $email): void
    {
        Assert::notContains($url, '/login', sprintf('Sign-in left no session — the dashboard bounced to "%s".', $url));
        Assert::notContains($url, '/2fa', sprintf('A second factor was demanded — the dashboard bounced to "%s".', $url));
        Assert::false(
            str_contains($content, self::CHALLENGE_MARKER),
            'The dashboard answered with the second-factor challenge.',
        );
        Assert::true(
            str_contains($content, $email),
            sprintf('The dashboard does not show "%s", so the session belongs to somebody else.', $email),
        );
    }
}
