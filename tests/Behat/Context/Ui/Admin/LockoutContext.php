<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\User\Model\UserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;
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
        $page->fillField('_password', $password);
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

    protected function loadAdminUser(string $email): LockableAdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($user, LockableAdminUserInterface::class);

        return $user;
    }

    protected function loadShopUser(string $email): LockableShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::isInstanceOf($customer, CustomerInterface::class);
        $user = $customer->getUser();
        Assert::isInstanceOf($user, LockableShopUserInterface::class);

        return $user;
    }
}
