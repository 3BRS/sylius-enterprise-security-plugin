<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Session;
use Sylius\Behat\Service\SharedStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use Webmozart\Assert\Assert;

class PasswordLoginContext implements Context
{
    protected const DISABLED_NOTICE_SELECTOR = '[data-test-three-brs-password-login-disabled]';

    public function __construct(
        protected Session $session,
        protected SettingsWriterInterface $settingsWriter,
        protected SettingsProviderInterface $settingsProvider,
        protected UrlGeneratorInterface $router,
        protected SharedStorageInterface $sharedStorage,
    ) {
    }

    /**
     * Password login defaults to ON; reset it before every scenario so a previous
     * "disabled" scenario cannot bleed into the next one.
     *
     * @BeforeScenario
     */
    public function resetPasswordLogin(): void
    {
        $this->setCustomerPasswordLogin(true);
    }

    /**
     * @Given password login is disabled for customers
     */
    public function passwordLoginIsDisabledForCustomers(): void
    {
        $this->setCustomerPasswordLogin(false);
    }

    /**
     * @When I attempt to sign in with email :email and password :password
     */
    public function iAttemptToSignIn(string $email, string $password): void
    {
        $this->session->visit($this->router->generate('sylius_shop_login'));

        $page = $this->session->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $this->retrieveSecurePassword($password));
        $page->pressButton('Login');
    }

    /**
     * @When I visit the shop login page
     */
    public function iVisitTheShopLoginPage(): void
    {
        $this->session->visit($this->router->generate('sylius_shop_login'));
    }

    /**
     * @When I visit the shop registration page
     */
    public function iVisitTheShopRegistrationPage(): void
    {
        $this->session->visit($this->router->generate('sylius_shop_register'));
    }

    /**
     * A crafted POST straight to the register endpoint — bypassing the hidden
     * form — to prove the server-side `RegistrationBlockListener` backstop, not
     * just the template gating.
     *
     * @When I submit a registration request
     */
    public function iSubmitARegistrationRequest(): void
    {
        $driver = $this->session->getDriver();
        Assert::isInstanceOf($driver, BrowserKitDriver::class);

        $driver->getClient()->request('POST', $this->router->generate('sylius_shop_register'));
    }

    /**
     * @Then I should be signed in to the shop
     */
    public function iShouldBeSignedIn(): void
    {
        Assert::notContains(
            $this->session->getCurrentUrl(),
            '/login',
            'Expected to be off the login page after a successful sign-in.',
        );
    }

    /**
     * @Then I should see the password-login-disabled notice
     */
    public function iShouldSeeTheDisabledNotice(): void
    {
        Assert::notNull(
            $this->session->getPage()->find('css', self::DISABLED_NOTICE_SELECTOR),
            'Expected the password-login-disabled notice to be shown.',
        );
    }

    /**
     * @Then the password login form should be hidden
     */
    public function thePasswordLoginFormShouldBeHidden(): void
    {
        Assert::null(
            $this->session->getPage()->find('css', 'input[name="_username"]'),
            'Expected the email + password login form to be hidden.',
        );
    }

    /**
     * @Then the forgot-password link should be hidden
     */
    public function theForgotPasswordLinkShouldBeHidden(): void
    {
        Assert::null(
            $this->session->getPage()->find('css', 'a[href*="forgotten-password"]'),
            'Expected the forgot-password link to be hidden.',
        );
    }

    /**
     * @Then I should be redirected to the shop login page
     */
    public function iShouldBeRedirectedToTheLoginPage(): void
    {
        Assert::contains(
            $this->session->getCurrentUrl(),
            '/login',
            'Expected the blocked registration to redirect to the login page.',
        );
    }

    /**
     * @Then I should see the registration-disabled error
     */
    public function iShouldSeeTheRegistrationDisabledError(): void
    {
        Assert::contains(
            (string) $this->session->getPage()->getContent(),
            'Registration with an email and password is currently disabled',
            'Expected the registration-disabled flash to be shown.',
        );
    }

    /**
     * @When I visit my account dashboard
     */
    public function iVisitMyAccountDashboard(): void
    {
        $this->session->visit($this->router->generate('sylius_shop_account_dashboard'));
    }

    /**
     * @Then the change-password entry should be hidden from my account
     */
    public function theChangePasswordEntryShouldBeHidden(): void
    {
        $page = $this->session->getPage();
        Assert::null(
            $page->find('css', 'a[href*="change-password"]'),
            'Expected the change-password entry to be hidden.',
        );
        Assert::null(
            $page->find('css', 'a[href*="set-password"]'),
            'Expected the set-password entry to be hidden.',
        );
    }

    protected function setCustomerPasswordLogin(bool $enabled): void
    {
        $this->settingsWriter->set('password_login.enabled', SettingsScope::CUSTOMER, $enabled);
        $this->settingsWriter->flush();
        $this->settingsProvider->refresh();
    }

    /**
     * Sylius's "there is a customer account … identified by …" step replaces the
     * configured password with a random secret and stashes the original → secret
     * mapping in shared storage; recover the real secret to submit it.
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
