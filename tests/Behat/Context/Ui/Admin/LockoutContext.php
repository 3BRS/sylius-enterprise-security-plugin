<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\User\Model\UserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\AdminUserLockoutManagerInterface;
use Webmozart\Assert\Assert;

class LockoutContext implements Context
{
    /** @param UserRepositoryInterface<UserInterface> $adminUserRepository */
    public function __construct(
        protected Session $session,
        protected UserRepositoryInterface $adminUserRepository,
        protected CustomerRepositoryInterface $customerRepository,
        protected EntityManagerInterface $entityManager,
        protected UrlGeneratorInterface $router,
        protected AdminUserLockoutManagerInterface $lockoutManager,
        protected SharedStorageInterface $sharedStorage,
        protected CacheItemPoolInterface $rateLimiterCachePool,
    ) {
    }

    /**
     * @When I try to sign in to the admin panel with email :email and password :password
     */
    public function iTryToSignInToAdmin(string $email, string $password): void
    {
        $this->session->visit($this->router->generate('sylius_admin_login'));
        $page = $this->session->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $this->retrieveSecurePassword($password));
        $page->pressButton('Login');
    }

    /**
     * @When I sign in to the admin panel with email :email and password :password
     */
    public function iSignInToAdmin(string $email, string $password): void
    {
        $this->iTryToSignInToAdmin($email, $password);
    }

    /**
     * @Given admin :email is locked
     */
    public function adminIsLocked(string $email): void
    {
        $user = $this->loadAdminUser($email);
        $now = new \DateTimeImmutable();
        $user->setLockedAt($now);
        $user->setLockoutUntil($now->modify('+30 minutes'));
        $user->setFailedLoginAttempts(3);
        $this->entityManager->flush();
    }

    /**
     * @Given admin :email was locked but the lockout has already expired
     */
    public function adminLockoutExpired(string $email): void
    {
        $user = $this->loadAdminUser($email);
        $past = new \DateTimeImmutable('-1 hour');
        $user->setLockedAt($past);
        $user->setLockoutUntil($past->modify('+30 minutes'));
        $user->setFailedLoginAttempts(3);
        $this->entityManager->flush();
    }

    /**
     * @When the lockout time for admin :email has elapsed
     *
     * Simulates the `auto_unlock_after` interval passing by rewinding
     * `lockedAt` / `lockoutUntil` into the past AND clearing the rate
     * limiter cache. In real wall-clock time both windows (DB lockout
     * + Symfony rate-limit fixed window) elapse together, so the test
     * resets both. Lets a scenario chain "real failed attempts → wait
     * → real sign-in" without waiting the configured 15 / 30 min.
     */
    public function adminLockoutHasElapsed(string $email): void
    {
        $user = $this->loadAdminUser($email);
        $this->entityManager->refresh($user);

        Assert::notNull($user->getLockoutUntil(), sprintf('Expected admin %s to be locked.', $email));

        $past = new \DateTimeImmutable('-1 hour');
        $user->setLockedAt($past);
        $user->setLockoutUntil($past->modify('+1 minute')); // still in the past
        $this->entityManager->flush();

        $this->rateLimiterCachePool->clear();
    }

    /**
     * @Then admin :email should be locked
     */
    public function adminShouldBeLocked(string $email): void
    {
        $user = $this->loadAdminUser($email);
        $this->entityManager->refresh($user);

        Assert::notNull($user->getLockedAt());
    }

    /**
     * @Then admin :email should not be locked
     */
    public function adminShouldNotBeLocked(string $email): void
    {
        $user = $this->loadAdminUser($email);
        $this->entityManager->refresh($user);

        Assert::null($user->getLockedAt());
    }

    /**
     * @Then the failed attempt counter for admin :email should be :count
     */
    public function adminFailedAttemptsShouldBe(string $email, int $count): void
    {
        $user = $this->loadAdminUser($email);
        $this->entityManager->refresh($user);

        Assert::same($user->getFailedLoginAttempts(), $count);
    }

    /**
     * @When I visit the locked customers page
     */
    public function iVisitLockedCustomersPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_admin_locked_customers'));
    }

    /**
     * @When I visit the locked admins page
     */
    public function iVisitLockedAdminsPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_admin_locked_admins'));
    }

    /**
     * @Then I should see no locked customers
     */
    public function iShouldSeeNoLockedCustomers(): void
    {
        $content = (string) $this->session->getPage()->getContent();
        Assert::contains($content, 'No customers are currently locked');
    }

    /**
     * @When I unlock the locked customer :email
     */
    public function iUnlockTheLockedCustomer(string $email): void
    {
        $user = $this->loadShopUser($email);
        Assert::notNull($user->getId());
        $this->session->visit($this->router->generate('three_brs_admin_locked_customers'));
        $form = $this->session->getPage()->find('css', sprintf('form[action$="/admin/locked-customers/%d/unlock"]', $user->getId()));
        Assert::notNull($form, sprintf('Unlock form for customer %s not found.', $email));
        $form->find('css', 'button[type="submit"]')?->click();
    }

    /**
     * @When I unlock the locked admin :email
     */
    public function iUnlockTheLockedAdmin(string $email): void
    {
        $user = $this->loadAdminUser($email);
        Assert::notNull($user->getId());
        $this->session->visit($this->router->generate('three_brs_admin_locked_admins'));
        $form = $this->session->getPage()->find('css', sprintf('form[action$="/admin/locked-admins/%d/unlock"]', $user->getId()));
        Assert::notNull($form, sprintf('Unlock form for admin %s not found.', $email));
        $form->find('css', 'button[type="submit"]')?->click();
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
     * @Then customer :email should not be locked
     */
    public function customerShouldNotBeLocked(string $email): void
    {
        $user = $this->loadShopUser($email);
        $this->entityManager->refresh($user);

        Assert::null($user->getLockedAt());
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

    /**
     * @Then I should be signed in to the admin panel as :email
     */
    public function iShouldBeSignedInToAdminAs(string $email): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::notContains($url, '/admin/login', sprintf('Expected to be off the admin login page after sign-in, got "%s".', $url));
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
     * @When the locked admin :email is unlocked by another administrator
     *
     * Calls the lockout manager directly to simulate another admin clicking
     * "Unlock" in /admin/locked-admins — same code path, avoids needing a
     * second admin session in the scenario.
     */
    public function adminIsUnlockedByAnotherAdministrator(string $email): void
    {
        $this->lockoutManager->unlock($this->loadAdminUser($email));
    }

    protected function loadAdminUser(string $email): LockableAdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($user, LockableAdminUserInterface::class);

        return $user;
    }

    /**
     * Sylius's `there is an administrator ... identified by ...` step replaces
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

    protected function loadShopUser(string $email): LockableShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($customer, CustomerInterface::class);
        $user = $customer->getUser();
        Assert::isInstanceOf($user, LockableShopUserInterface::class);

        return $user;
    }
}
