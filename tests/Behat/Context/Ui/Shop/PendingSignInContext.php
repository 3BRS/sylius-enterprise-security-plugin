<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

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
     * @When I open the shop homepage
     */
    public function iOpenTheShopHomepage(): void
    {
        $this->session->visit($this->router->generate('sylius_shop_homepage', ['_locale' => 'en_US']));
    }

    /**
     * @When I open the shop login page
     */
    public function iOpenTheShopLoginPage(): void
    {
        $this->session->visit($this->router->generate('sylius_shop_login', ['_locale' => 'en_US']));
    }

    /**
     * @When I open my account dashboard
     */
    public function iOpenMyAccountDashboard(): void
    {
        $this->session->visit($this->router->generate('sylius_shop_account_dashboard', ['_locale' => 'en_US']));
    }

    /**
     * @When the code page sends a background request to the shop homepage
     */
    public function theCodePageSendsABackgroundRequestToTheShopHomepage(): void
    {
        $this->getClient()->request(
            'GET',
            $this->router->generate('sylius_shop_homepage', ['_locale' => 'en_US']),
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );
    }

    /**
     * @Then I should be on the shop homepage
     */
    public function iShouldBeOnTheShopHomepage(): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::same(
            parse_url($url, \PHP_URL_PATH),
            $this->router->generate('sylius_shop_homepage', ['_locale' => 'en_US']),
            sprintf('Expected the shop homepage, got "%s".', $url),
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
