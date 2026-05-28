<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerLoginPreference;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerLoginPreferenceRepositoryInterface;
use Webmozart\Assert\Assert;

class PasswordLoginControlContext implements Context
{
    public function __construct(
        protected Session $session,
        protected CustomerRepositoryInterface $customerRepository,
        protected EntityManagerInterface $entityManager,
        protected UrlGeneratorInterface $router,
        protected CustomerLoginPreferenceRepositoryInterface $preferenceRepository,
        protected SharedStorageInterface $sharedStorage,
    ) {
    }

    /**
     * @Given password login is disabled for customer :email
     */
    public function passwordLoginIsDisabledForCustomer(string $email): void
    {
        $shopUser = $this->loadShopUser($email);

        $preference = $this->preferenceRepository->findOneByShopUser($shopUser);
        if ($preference === null) {
            $preference = new CustomerLoginPreference();
            $preference->setShopUser($shopUser);
            $this->entityManager->persist($preference);
        }
        $preference->setPasswordLoginAllowed(false);
        $this->entityManager->flush();
    }

    /**
     * @When I try to sign in with email :email and password :password
     */
    public function iTryToSignInWithEmailAndPassword(string $email, string $password): void
    {
        $this->session->visit($this->router->generate('sylius_shop_login'));

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
     * @Then I should be signed in to the shop as :email
     */
    public function iShouldBeSignedInAs(string $email): void
    {
        $url = (string) $this->session->getCurrentUrl();
        Assert::notContains($url, '/login', sprintf('Expected to be off the login page after sign-in, got "%s".', $url));
    }

    /**
     * @Then I should not be signed in to the shop
     */
    public function iShouldNotBeSignedIn(): void
    {
        $url = (string) $this->session->getCurrentUrl();
        Assert::contains($url, '/login', sprintf('Expected to stay on the login page; got "%s".', $url));
    }

    /**
     * @Then I should see the password-login-disabled message
     */
    public function iShouldSeeThePasswordLoginDisabledMessage(): void
    {
        $content = (string) $this->session->getPage()->getContent();
        Assert::contains($content, 'Password login is disabled', 'Expected the password-login-disabled message on the login page.');
    }

    protected function loadShopUser(string $email): ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($customer, CustomerInterface::class);

        $user = $customer->getUser();
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }

    /**
     * Sylius's `there is a customer account ... identified by ...` step replaces the
     * configured password with a random hex string and stashes the original → secret
     * mapping in shared storage. To submit the *real* password from a Behat step, we
     * look up the secret via that mapping.
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
