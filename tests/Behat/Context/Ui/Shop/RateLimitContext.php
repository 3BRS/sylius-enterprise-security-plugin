<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\MagicLinkRequestFormTrait;
use Webmozart\Assert\Assert;

class RateLimitContext implements Context
{
    use MagicLinkRequestFormTrait;

    public function __construct(
        protected Session $session,
        protected CustomerRepositoryInterface $customerRepository,
        protected EntityManagerInterface $entityManager,
        protected UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @Given the customer login rate limit is set to :limit requests per minute
     *
     * Marker step — actual limits come from `three_brs_sylius_enterprise_security_plugin.yaml`
     * in the test app. The step exists so the scenario reads naturally.
     */
    public function customerLoginRateLimitIsSet(int $limit): void
    {
        Assert::greaterThan($limit, 0);
    }

    /**
     * @When I try to sign in with email :email and password :password
     */
    public function iTryToSignIn(string $email, string $password): void
    {
        $this->session->visit($this->router->generate('sylius_shop_login', ['_locale' => 'en_US']));
        $page = $this->session->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $password);
        $page->pressButton('Login');
    }

    /**
     * @When I ask for a password reset for :email
     *
     * The shop's forgotten-password form. Its POST is what
     * `RateLimitListener::ROUTE_MAP` throttles as customer.password_reset, keyed on
     * the client address rather than on the address typed into the form.
     */
    public function iAskForAPasswordResetFor(string $email): void
    {
        $this->session->visit($this->router->generate('sylius_shop_request_password_reset_token', ['_locale' => 'en_US']));

        $page = $this->session->getPage();
        $page->fillField('sylius_user_request_password_reset[email]', $email);

        $submit = $page->find('css', '[data-test-request-password-reset-button]');
        Assert::notNull($submit, 'Password reset submit button not found.');
        $submit->click();
    }

    /**
     * @When I ask for a magic link for :email
     *
     * Deliberately worded differently from `I request a magic link for :email` in
     * Shop\MagicLinkContext. The request is the same and shared through the trait;
     * the wording is not, so the two contexts stay usable in one suite.
     */
    public function iAskForAMagicLinkFor(string $email): void
    {
        $this->submitMagicLinkRequestForm($email);
    }

    /**
     * @Then I should see the too-many-requests message
     */
    public function iShouldSeeTooManyRequestsMessage(): void
    {
        $statusCode = $this->session->getStatusCode();
        $content = (string) $this->session->getPage()->getContent();

        Assert::true(
            $statusCode === 429 || str_contains($content, 'Too many requests'),
            sprintf('Expected 429 status or rate-limit flash; got %d.', $statusCode),
        );
    }

    /**
     * @Then the request should not have been refused
     *
     * The half that keeps the scenario from passing on a limiter that refuses
     * everything, or one that never fires: the requests before the limit is spent
     * have to go through.
     */
    public function theRequestShouldNotHaveBeenRefused(): void
    {
        $statusCode = $this->session->getStatusCode();
        $content = (string) $this->session->getPage()->getContent();

        Assert::notSame($statusCode, 429, 'The request was refused with 429 before the limit was spent.');
        Assert::false(
            str_contains($content, 'Too many requests'),
            'The rate-limit message was shown before the limit was spent.',
        );
    }

    protected function getSession(): Session
    {
        return $this->session;
    }
}
