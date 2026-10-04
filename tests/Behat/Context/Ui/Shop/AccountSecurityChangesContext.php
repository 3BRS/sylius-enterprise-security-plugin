<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Session;
use Sylius\Behat\Context\Ui\Admin\Helper\SecurePasswordTrait;
use Sylius\Behat\Service\SharedStorageInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Webmozart\Assert\Assert;

class AccountSecurityChangesContext implements Context
{
    use SecurePasswordTrait;

    protected const REMEMBER_ME_COOKIE = 'APP_SHOP_REMEMBER_ME';

    public function __construct(
        protected Session $session,
        protected RouterInterface $router,
        protected SharedStorageInterface $sharedStorage,
    ) {
    }

    /**
     * @Given I am signed in to the shop through a remember-me cookie as :email with password :password
     */
    public function iAmSignedInThroughARememberMeCookie(string $email, string $password): void
    {
        $this->submitLoginForm($email, $password, true);

        Assert::notNull(
            $this->session->getCookie(self::REMEMBER_ME_COOKIE),
            sprintf('No remember-me cookie after signing in (URL "%s").', $this->session->getCurrentUrl()),
        );

        // Without the session cookie the next request is signed in by the remember-me cookie alone.
        $this->session->setCookie((string) ini_get('session.name'), null);
    }

    /**
     * @When I sign in to the shop with email :email and password :password
     */
    public function iSignInToTheShop(string $email, string $password): void
    {
        $this->submitLoginForm($email, $password, false);
    }

    /**
     * @When I start linking my :provider account
     */
    public function iStartLinkingMyAccount(string $provider): void
    {
        $this->session->visit($this->router->generate('three_brs_shop_oauth_initiate', [
            'provider' => $provider,
            'intent' => 'link',
        ]));
    }

    /**
     * @When I request passkey registration options
     */
    public function iRequestPasskeyRegistrationOptions(): void
    {
        $this->postJson($this->router->generate('three_brs_shop_passkey_register_options'));
    }

    /**
     * @When I submit a passkey registration
     */
    public function iSubmitAPasskeyRegistration(): void
    {
        $this->postJson($this->router->generate('three_brs_shop_passkey_register_verify'), '{}');
    }

    /**
     * @Then the passkey registration should not be refused
     */
    public function thePasskeyRegistrationShouldNotBeRefused(): void
    {
        // The empty payload gets past the access check and fails on the missing credential.
        Assert::same($this->session->getStatusCode(), Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Then the passkey registration options should be issued
     */
    public function thePasskeyRegistrationOptionsShouldBeIssued(): void
    {
        Assert::same($this->session->getStatusCode(), Response::HTTP_OK);
        Assert::contains($this->session->getPage()->getContent(), '"challenge"');
    }

    /**
     * @Then I should be on the shop login page
     */
    public function iShouldBeOnTheShopLoginPage(): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::same(
            parse_url($url, \PHP_URL_PATH),
            $this->router->generate('sylius_shop_login', ['_locale' => 'en_US']),
            sprintf('Expected the shop login page, got "%s".', $url),
        );
        Assert::notNull($this->session->getPage()->findField('_password'), 'The login page shows no login form.');
    }

    protected function submitLoginForm(string $email, string $password, bool $rememberMe): void
    {
        $this->session->visit($this->router->generate('sylius_shop_login', ['_locale' => 'en_US']));
        $page = $this->session->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $this->retrieveSecurePassword($password));
        if ($rememberMe) {
            $page->checkField('_remember_me');
        }
        $page->pressButton('Login');
    }

    protected function postJson(string $url, string $content = ''): void
    {
        $this->getDriver()->getClient()->request('POST', $url, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $content);
    }

    protected function getDriver(): BrowserKitDriver
    {
        $driver = $this->session->getDriver();
        Assert::isInstanceOf($driver, BrowserKitDriver::class);

        return $driver;
    }
}
