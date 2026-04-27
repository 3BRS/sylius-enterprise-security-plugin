<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;
use Webmozart\Assert\Assert;

class LockoutContext implements Context
{
    public function __construct(
        protected Session $session,
        protected CustomerRepositoryInterface $customerRepository,
        protected EntityManagerInterface $entityManager,
        protected UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @When I try to sign in with email :email and password :password
     */
    public function iTryToSignInWithEmailAndPassword(string $email, string $password): void
    {
        $this->session->visit($this->router->generate('sylius_shop_login'));

        $page = $this->session->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $password);
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
     * @Given customer :email has :count failed login attempts
     */
    public function customerHasFailedAttempts(string $email, int $count): void
    {
        $user = $this->loadShopUser($email);
        $user->setFailedLoginAttempts($count);
        $user->setLastFailedLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();
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
     * @Then customer :email should not be locked
     */
    public function customerShouldNotBeLocked(string $email): void
    {
        $user = $this->loadShopUser($email);
        $this->entityManager->refresh($user);

        Assert::null($user->getLockedAt(), sprintf('Expected customer %s to be unlocked.', $email));
    }

    /**
     * @Then the failed attempt counter for customer :email should be :count
     */
    public function failedAttemptCounterShouldBe(string $email, int $count): void
    {
        $user = $this->loadShopUser($email);
        $this->entityManager->refresh($user);

        Assert::same($user->getFailedLoginAttempts(), $count);
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
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::isInstanceOf($customer, CustomerInterface::class);
        $user = $customer->getUser();
        Assert::isInstanceOf($user, LockableShopUserInterface::class);

        return $user;
    }
}
