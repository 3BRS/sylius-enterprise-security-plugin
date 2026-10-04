<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Session;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Routing\RouterInterface;
use Webmozart\Assert\Assert;

class PendingSignInContext implements Context
{
    /** @var array<string, mixed>|null */
    protected ?array $serverParametersBeforeScenario = null;

    public function __construct(
        protected Session $session,
        protected RouterInterface $router,
    ) {
    }

    /**
     * The kernel browser sends no Accept header unless told to, and the pending sign-in is ended
     * only by page loads, which a browser marks with `Accept: text/html`. Set on the client, the
     * header also goes with submitted forms and followed redirects.
     *
     * @Given my browser asks for HTML pages
     */
    public function myBrowserAsksForHtmlPages(): void
    {
        $client = $this->getClient();
        if ($this->serverParametersBeforeScenario === null) {
            $parameters = $this->getServerParameters()->getValue($client);
            Assert::isArray($parameters);
            $this->serverParametersBeforeScenario = $parameters;
        }
        $client->setServerParameter('HTTP_ACCEPT', 'text/html,application/xhtml+xml');
    }

    #[AfterScenario]
    public function restoreServerParameters(): void
    {
        if ($this->serverParametersBeforeScenario === null) {
            return;
        }

        $this->getServerParameters()->setValue($this->getClient(), $this->serverParametersBeforeScenario);
        $this->serverParametersBeforeScenario = null;
    }

    /**
     * @When I open the admin dashboard
     */
    public function iOpenTheAdminDashboard(): void
    {
        $this->session->visit($this->router->generate('sylius_admin_dashboard'));
    }

    /**
     * @When I open the admin login page
     */
    public function iOpenTheAdminLoginPage(): void
    {
        $this->session->visit($this->router->generate('sylius_admin_login'));
    }

    /**
     * @When the admin code page sends a background request to the admin dashboard
     */
    public function theAdminCodePageSendsABackgroundRequestToTheAdminDashboard(): void
    {
        $this->getClient()->request(
            'GET',
            $this->router->generate('sylius_admin_dashboard'),
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );
    }

    protected function getClient(): AbstractBrowser
    {
        $driver = $this->session->getDriver();
        Assert::isInstanceOf($driver, BrowserKitDriver::class);

        return $driver->getClient();
    }

    protected function getServerParameters(): \ReflectionProperty
    {
        return new \ReflectionProperty(AbstractBrowser::class, 'server');
    }
}
