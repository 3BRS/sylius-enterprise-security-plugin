<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Routing\RouterInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Service\Passkey\FakeAuthenticator;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepositoryInterface;
use Webmozart\Assert\Assert;

class PasskeyCeremonyContext implements Context
{
    protected const FIREWALL_NAME = 'shop';

    /** @var array<string, string> map of label → credentialId base64url */
    protected array $credentialsByLabel = [];

    public function __construct(
        protected AbstractBrowser $client,
        protected RouterInterface $router,
        protected EntityManagerInterface $entityManager,
        protected CustomerRepositoryInterface $customerRepository,
        protected CustomerPasskeyCredentialRepositoryInterface $credentialRepository,
        protected FakeAuthenticator $fakeAuthenticator,
    ) {
    }

    /**
     * @BeforeScenario
     */
    public function resetFakeAuthenticator(BeforeScenarioScope $scope): void
    {
        FakeAuthenticator::reset();
        $this->credentialsByLabel = [];
    }

    /**
     * @When I am logged in to the shop as :email
     */
    public function iAmLoggedInAs(string $email): void
    {
        $shopUser = $this->findShopUser($email);
        $this->kernelBrowser()->loginUser($shopUser, self::FIREWALL_NAME);
    }

    /**
     * @When I sign out of the shop
     */
    public function iSignOut(): void
    {
        // KernelBrowser preserves cookies across requests; reset the session
        // by simulating a fresh client. /logout would also work but requires
        // the firewall to expose it without CSRF.
        $this->client->restart();
    }

    /**
     * @When I register a shop passkey labelled :label
     */
    public function iRegisterAShopPasskey(string $label): void
    {
        $options = $this->postJson($this->router->generate('three_brs_shop_passkey_register_options'), null);
        Assert::isArray($options, 'Registration options endpoint did not return JSON.');

        $origin = $this->resolveOrigin();
        $registrationResponse = $this->fakeAuthenticator->createRegistrationResponse($options, $origin);

        $verifyResponse = $this->postJson(
            $this->router->generate('three_brs_shop_passkey_register_verify'),
            [
                'label' => $label,
                'credential' => (string) json_encode($registrationResponse, JSON_THROW_ON_ERROR),
            ],
        );

        Assert::isArray($verifyResponse);
        Assert::true(($verifyResponse['ok'] ?? false) === true, sprintf(
            'Registration failed: %s',
            (string) json_encode($verifyResponse),
        ));

        $this->credentialsByLabel[$label] = (string) $registrationResponse['id'];
    }

    /**
     * @Then a shop passkey labelled :label should be stored for :email
     */
    public function aShopPasskeyShouldBeStoredFor(string $label, string $email): void
    {
        $this->entityManager->clear();
        $shopUser = $this->findShopUser($email);

        $stored = $this->credentialRepository->findAllByShopUser($shopUser);
        $matching = array_filter($stored, static fn ($credential) => $credential->getLabel() === $label);
        Assert::notEmpty($matching, sprintf('No passkey labelled "%s" stored for "%s".', $label, $email));
    }

    /**
     * @When I sign in to the shop with the passkey :label
     */
    public function iSignInWithTheShopPasskey(string $label): void
    {
        $credentialId = $this->credentialsByLabel[$label] ?? null;
        Assert::notNull($credentialId, sprintf('No passkey "%s" registered in this scenario.', $label));

        $options = $this->postJson($this->router->generate('three_brs_shop_passkey_login_options'), null);
        Assert::isArray($options);

        $origin = $this->resolveOrigin();
        $assertionResponse = $this->fakeAuthenticator->createLoginResponse($options, $credentialId, $origin);

        $verifyResponse = $this->postJson(
            $this->router->generate('three_brs_shop_passkey_login_verify'),
            ['credential' => (string) json_encode($assertionResponse, JSON_THROW_ON_ERROR)],
        );

        Assert::isArray($verifyResponse);
        Assert::true(($verifyResponse['ok'] ?? false) === true, sprintf(
            'Login failed: %s',
            (string) json_encode($verifyResponse),
        ));
    }

    /**
     * @When I attempt to sign in to the shop with an unknown passkey
     */
    public function iAttemptToSignInWithAnUnknownPasskey(): void
    {
        $options = $this->postJson($this->router->generate('three_brs_shop_passkey_login_options'), null);
        Assert::isArray($options);

        // Make a fresh keypair that the server does not know.
        $unknownRegistration = $this->fakeAuthenticator->createRegistrationResponse(
            ['rp' => ['id' => $options['rpId'] ?? parse_url($this->resolveOrigin(), PHP_URL_HOST)], 'challenge' => $options['challenge']],
            $this->resolveOrigin(),
        );
        $unknownCredentialId = (string) $unknownRegistration['id'];

        $assertionResponse = $this->fakeAuthenticator->createLoginResponse($options, $unknownCredentialId, $this->resolveOrigin());

        $this->postJson(
            $this->router->generate('three_brs_shop_passkey_login_verify'),
            ['credential' => (string) json_encode($assertionResponse, JSON_THROW_ON_ERROR)],
        );
    }

    /**
     * @Then the last passkey login should have failed
     */
    public function theLastPasskeyLoginShouldHaveFailed(): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        Assert::same($status, 400, sprintf('Expected HTTP 400 from rejected passkey login, got %d.', $status));
    }

    /**
     * @Then I should be logged in to the shop as :email
     */
    public function iShouldBeLoggedInAs(string $email): void
    {
        // A logged-in user reaches the dashboard; an anonymous user is redirected to /login.
        $this->client->request('GET', $this->router->generate('sylius_shop_account_dashboard', ['_locale' => 'en_US']));
        $finalUrl = (string) $this->client->getRequest()->getUri();
        Assert::notContains($finalUrl, '/login', sprintf('Expected to be on the account dashboard, got "%s".', $finalUrl));

        $shopUser = $this->findShopUser($email);
        Assert::notNull($shopUser, sprintf('Shop user "%s" not found in DB.', $email));
    }

    /**
     * The assertion above cannot tell a live session from a lost one: with
     * redirects off, getRequest() is the request just sent, so the dashboard URI
     * it reads never contains /login however the response came back. This one
     * follows the redirect and reads where it landed.
     *
     * It also has to live in this context. `test.client` is declared share(false),
     * so every constructor that asks for one gets its own KernelBrowser with its
     * own cookie jar — the session the ceremony opened is only visible on the
     * client the ceremony used.
     *
     * @Then the passkey sign-in should have skipped the second factor for :email
     */
    public function thePasskeySignInShouldHaveSkippedTheSecondFactorFor(string $email): void
    {
        $following = $this->client->isFollowingRedirects();
        $this->client->followRedirects(true);

        try {
            $this->client->request('GET', $this->router->generate('sylius_shop_account_dashboard', ['_locale' => 'en_US']));
        } finally {
            $this->client->followRedirects($following);
        }

        $url = (string) $this->client->getRequest()->getUri();
        $content = $this->client->getInternalResponse()->getContent();

        Assert::notContains($url, '/login', sprintf('The passkey sign-in left no session — the dashboard bounced to "%s".', $url));
        Assert::notContains($url, '/2fa', sprintf('A second factor was demanded — the dashboard bounced to "%s".', $url));
        Assert::false(
            str_contains($content, 'data-test-two-factor-challenge'),
            'The dashboard answered with the second-factor challenge.',
        );
        Assert::true(
            str_contains($content, $email),
            sprintf('The dashboard does not show "%s", so the session belongs to somebody else.', $email),
        );
    }

    protected function postJson(string $path, ?array $body): mixed
    {
        $this->client->request(
            'POST',
            $path,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $body === null ? '' : (string) json_encode($body, JSON_THROW_ON_ERROR),
        );

        $content = (string) $this->client->getResponse()->getContent();
        if ($content === '') {
            return null;
        }

        return json_decode($content, true);
    }

    protected function resolveOrigin(): string
    {
        return 'http://localhost';
    }

    protected function kernelBrowser(): KernelBrowser
    {
        Assert::isInstanceOf($this->client, KernelBrowser::class, 'Expected the test client to be KernelBrowser for loginUser support.');

        return $this->client;
    }

    protected function findShopUser(string $email): ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));

        $user = $customer->getUser();
        Assert::notNull($user);
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }
}
