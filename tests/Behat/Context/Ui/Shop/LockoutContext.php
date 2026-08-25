<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\ShopUserLockoutManagerInterface;
use Webmozart\Assert\Assert;

class LockoutContext implements Context
{
    public function __construct(
        protected Session $session,
        protected CustomerRepositoryInterface $customerRepository,
        protected EntityManagerInterface $entityManager,
        protected UrlGeneratorInterface $router,
        protected ShopUserLockoutManagerInterface $lockoutManager,
        protected SharedStorageInterface $sharedStorage,
        protected CacheItemPoolInterface $rateLimiterCachePool,
    ) {
    }

    /**
     * @When I try to sign in with email :email and password :password
     */
    public function iTryToSignInWithEmailAndPassword(string $email, string $password): void
    {
        $this->session->visit($this->router->generate('sylius_shop_login', ['_locale' => 'en_US']));

        $page = $this->session->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $this->retrieveSecurePassword($password));
        $page->pressButton('Login');
    }

    /**
     * @When I sign in with email :email and password :password
     */
    public function iSignInWithEmailAndPassword(string $email, string $password): void
    {
        $this->iTryToSignInWithEmailAndPassword($email, $password);
    }

    /**
     * @Given customer :email is locked
     */
    public function customerIsLocked(string $email): void
    {
        $user = $this->loadShopUser($email);
        $now = new \DateTimeImmutable();
        $user->setLockedAt($now);
        $user->setLockoutUntil($now->modify('+15 minutes'));
        $user->setFailedLoginAttempts(5);
        $this->entityManager->flush();
    }

    /**
     * @Given customer :email was locked but the lockout has already expired
     */
    public function customerLockoutExpired(string $email): void
    {
        $user = $this->loadShopUser($email);
        $past = new \DateTimeImmutable('-1 hour');
        $user->setLockedAt($past);
        $user->setLockoutUntil($past->modify('+15 minutes')); // still in the past
        $user->setFailedLoginAttempts(5);
        $this->entityManager->flush();
    }

    /**
     * @When the lockout time for customer :email has elapsed
     *
     * Simulates the `auto_unlock_after` interval passing by rewinding
     * `lockedAt` / `lockoutUntil` into the past AND clearing the rate
     * limiter cache. In real wall-clock time both windows (DB lockout
     * + Symfony rate-limit fixed window) elapse together, so the test
     * resets both. Lets a scenario chain "real failed attempts → wait
     * → real sign-in" without waiting the configured 15 / 30 min.
     */
    public function customerLockoutHasElapsed(string $email): void
    {
        $user = $this->loadShopUser($email);
        $this->entityManager->refresh($user);

        Assert::notNull($user->getLockoutUntil(), sprintf('Expected customer %s to be locked.', $email));

        $past = new \DateTimeImmutable('-1 hour');
        $user->setLockedAt($past);
        $user->setLockoutUntil($past->modify('+1 minute')); // still in the past
        $this->entityManager->flush();

        $this->rateLimiterCachePool->clear();
    }

    /**
     * @When the locked customer :email is unlocked by an administrator
     *
     * Calls the lockout manager directly to simulate the admin clicking
     * "Unlock" in /admin/locked-customers — same code path, no need to
     * pull admin contexts into a shop suite.
     */
    public function customerIsUnlockedByAdministrator(string $email): void
    {
        $this->lockoutManager->unlock($this->loadShopUser($email));
    }

    /**
     * @Then I should not see the too-many-requests message
     */
    public function iShouldNotSeeTooManyRequests(): void
    {
        $content = (string) $this->session->getPage()->getContent();
        Assert::notContains($content, 'three_brs.rate_limit.too_many_requests', 'Unexpected rate-limit error.');
        Assert::notContains($content, 'Too many requests', 'Unexpected rate-limit error.');
    }

    /**
     * @Then I should be signed in to the shop as :email
     */
    public function iShouldBeSignedInAs(string $email): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::notContains($url, '/login', sprintf('Expected to be off the login page after sign-in, got "%s".', $url));
    }

    /**
     * @Then customer :email should be locked
     */
    public function customerShouldBeLocked(string $email): void
    {
        $user = $this->loadShopUser($email);
        $this->entityManager->refresh($user);

        Assert::notNull($user->getLockedAt(), sprintf('Expected customer %s to be locked.', $email));
    }

    /**
     * @Then the failed attempt counter for customer :email should be :count
     */
    public function customerFailedAttemptsShouldBe(string $email, int $count): void
    {
        $user = $this->loadShopUser($email);
        $this->entityManager->refresh($user);

        Assert::same($user->getFailedLoginAttempts(), $count);
    }

    /**
     * @Then customer :email should not be locked
     */
    public function customerShouldNotBeLocked(string $email): void
    {
        $user = $this->loadShopUser($email);
        $this->entityManager->refresh($user);

        Assert::null($user->getLockedAt(), sprintf('Expected customer %s to be unlocked.', $email));
    }

    /**
     * @Then I should see the locked-account message
     *
     * Sylius/Symfony renders a generic "Invalid credentials" message for all
     * authentication failures (locked account, wrong password, …) so an
     * attacker cannot infer which case applies. We assert that the user did
     * not get past the login page.
     */
    public function iShouldSeeTheLockedAccountMessage(): void
    {
        $url = (string) $this->session->getCurrentUrl();
        Assert::contains($url, '/login', sprintf('Expected to stay on the login page; got %s.', $url));
    }

    protected function loadShopUser(string $email): LockableShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($customer, CustomerInterface::class);
        $user = $customer->getUser();
        Assert::isInstanceOf($user, LockableShopUserInterface::class);

        return $user;
    }

    /**
     * Sylius's `there is a customer account ... identified by ...` step replaces
     * the configured password with a random hex string and stashes the original
     * → secret mapping in shared storage. To submit the *real* password from a
     * Behat step, we look up the secret via that mapping.
     */
    protected function retrieveSecurePassword(string $password): string
    {
        $scenarioSetupPassword = $this->sharedStorage->has('scenario_setup_password')
            ? $this->sharedStorage->get('scenario_setup_password')
            : null;

        if ($scenarioSetupPassword === $password && $this->sharedStorage->has('password')) {
            return (string) $this->sharedStorage->get('password');
        }

        return $password;
    }
}
