<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Routing\RouterInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Service\Passkey\FakeAuthenticator;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use Webmozart\Assert\Assert;

class PasskeyCeremonyContext implements Context
{
    protected const FIREWALL_NAME = 'admin';

    /** @var array<string, string> map of label → credentialId base64url */
    protected array $credentialsByLabel = [];

    /**
     * @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository
     */
    public function __construct(
        protected AbstractBrowser $client,
        protected RouterInterface $router,
        protected EntityManagerInterface $entityManager,
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserPasskeyCredentialRepositoryInterface $credentialRepository,
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
     * @When I am logged in to the admin as :email
     */
    public function iAmLoggedInAs(string $email): void
    {
        $adminUser = $this->findAdminUser($email);
        $this->kernelBrowser()->loginUser($adminUser, self::FIREWALL_NAME);
    }

    /**
     * @When I sign out of the admin
     */
    public function iSignOut(): void
    {
        $this->client->restart();
    }

    /**
     * @When I register an admin passkey labelled :label
     */
    public function iRegisterAnAdminPasskey(string $label): void
    {
        $options = $this->postJson($this->router->generate('three_brs_admin_passkey_register_options'), null);
        Assert::isArray($options, 'Registration options endpoint did not return JSON.');

        $origin = $this->resolveOrigin();
        $registrationResponse = $this->fakeAuthenticator->createRegistrationResponse($options, $origin);

        $verifyResponse = $this->postJson(
            $this->router->generate('three_brs_admin_passkey_register_verify'),
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
     * @Then an admin passkey labelled :label should be stored for :email
     */
    public function anAdminPasskeyShouldBeStoredFor(string $label, string $email): void
    {
        $this->entityManager->clear();
        $adminUser = $this->findAdminUser($email);

        $stored = $this->credentialRepository->findAllByAdminUser($adminUser);
        $matching = array_filter($stored, static fn ($credential) => $credential->getLabel() === $label);
        Assert::notEmpty($matching, sprintf('No passkey labelled "%s" stored for "%s".', $label, $email));
    }

    /**
     * @When I sign in to the admin with the passkey :label
     */
    public function iSignInWithTheAdminPasskey(string $label): void
    {
        $credentialId = $this->credentialsByLabel[$label] ?? null;
        Assert::notNull($credentialId, sprintf('No passkey "%s" registered in this scenario.', $label));

        $options = $this->postJson($this->router->generate('three_brs_admin_passkey_login_options'), null);
        Assert::isArray($options);

        $origin = $this->resolveOrigin();
        $assertionResponse = $this->fakeAuthenticator->createLoginResponse($options, $credentialId, $origin);

        $verifyResponse = $this->postJson(
            $this->router->generate('three_brs_admin_passkey_login_verify'),
            ['credential' => (string) json_encode($assertionResponse, JSON_THROW_ON_ERROR)],
        );

        Assert::isArray($verifyResponse);
        Assert::true(($verifyResponse['ok'] ?? false) === true, sprintf(
            'Login failed: %s',
            (string) json_encode($verifyResponse),
        ));
    }

    /**
     * @When I attempt to sign in to the admin with an unknown passkey
     */
    public function iAttemptToSignInWithAnUnknownPasskey(): void
    {
        $options = $this->postJson($this->router->generate('three_brs_admin_passkey_login_options'), null);
        Assert::isArray($options);

        $unknownRegistration = $this->fakeAuthenticator->createRegistrationResponse(
            ['rp' => ['id' => $options['rpId'] ?? parse_url($this->resolveOrigin(), PHP_URL_HOST)], 'challenge' => $options['challenge']],
            $this->resolveOrigin(),
        );
        $unknownCredentialId = (string) $unknownRegistration['id'];

        $assertionResponse = $this->fakeAuthenticator->createLoginResponse($options, $unknownCredentialId, $this->resolveOrigin());

        $this->postJson(
            $this->router->generate('three_brs_admin_passkey_login_verify'),
            ['credential' => (string) json_encode($assertionResponse, JSON_THROW_ON_ERROR)],
        );
    }

    /**
     * @Then the last admin passkey login should have failed
     */
    public function theLastPasskeyLoginShouldHaveFailed(): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        Assert::same($status, 400, sprintf('Expected HTTP 400 from rejected passkey login, got %d.', $status));

        // The status is what the sign-in button reads, not what decides who is
        // signed in. A verify that answered 400 and still wrote the token would
        // satisfy the line above and hand the panel to whoever asked.
        [$url] = $this->visitAdminDashboard();
        Assert::contains($url, '/login', sprintf('The refused passkey still opened a session — the dashboard answered at "%s".', $url));
    }

    /**
     * @Then I should be logged in to the admin as :email
     */
    public function iShouldBeLoggedInAs(string $email): void
    {
        Assert::notNull($this->findAdminUser($email), sprintf('Administrator "%s" not found.', $email));

        [$url, $status] = $this->visitAdminDashboard();

        Assert::notContains($url, '/login', sprintf('The passkey sign-in left no session — the dashboard bounced to "%s".', $url));
        Assert::same(200, $status, sprintf('The admin dashboard answered %d after the passkey sign-in.', $status));
    }

    /**
     * Asks for the admin dashboard and reports where the answer came from.
     *
     * The redirect has to be followed. Without it `getRequest()` returns the
     * request that was just sent, so the URI read back is the dashboard's own
     * however the response came — an assertion built on it cannot fail.
     *
     * @return array{0: string, 1: int} final URI and the status behind it
     */
    protected function visitAdminDashboard(): array
    {
        $following = $this->client->isFollowingRedirects();
        $this->client->followRedirects(true);

        try {
            $this->client->request('GET', $this->router->generate('sylius_admin_dashboard'));
        } finally {
            $this->client->followRedirects($following);
        }

        return [
            (string) $this->client->getRequest()->getUri(),
            $this->client->getInternalResponse()->getStatusCode(),
        ];
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

    protected function findAdminUser(string $email): AdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($user, sprintf('Admin user "%s" not found.', $email));
        Assert::isInstanceOf($user, AdminUserInterface::class);

        return $user;
    }
}
