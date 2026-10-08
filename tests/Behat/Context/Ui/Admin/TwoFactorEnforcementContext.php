<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use Symfony\Contracts\Translation\TranslatorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorMode;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use Webmozart\Assert\Assert;

class TwoFactorEnforcementContext implements Context
{
    public function __construct(
        protected Session $session,
        protected SettingsWriterInterface $settingsWriter,
        protected SettingsProviderInterface $settingsProvider,
        protected TranslatorInterface $translator,
    ) {
    }

    /**
     * @BeforeScenario
     */
    public function resetEnforcement(): void
    {
        $this->setMode(TwoFactorMode::ALLOWED);
    }

    /**
     * @Given 2FA enforcement is enabled for admins
     */
    public function enforcementIsEnabledForAdmins(): void
    {
        $this->setMode(TwoFactorMode::ENFORCED);
    }

    /**
     * @When I visit the admin dashboard
     */
    public function iVisitTheAdminDashboard(): void
    {
        $this->session->visit('/admin/');
    }

    /**
     * @Then I should be redirected to the admin 2FA setup page
     */
    public function iShouldBeRedirectedToTheSetupPage(): void
    {
        Assert::contains($this->session->getCurrentUrl(), '/admin/two-factor/setup');
    }

    /**
     * Each blocked page answers with a redirect to the setup page; the redirects are not followed,
     * so the setup page opens only after both.
     *
     * @When I open the admin dashboard and the order list before the setup page
     */
    public function iOpenTheAdminDashboardAndTheOrderListBeforeTheSetupPage(): void
    {
        $driver = $this->session->getDriver();
        Assert::isInstanceOf($driver, BrowserKitDriver::class);
        $client = $driver->getClient();

        $following = $client->isFollowingRedirects();
        $client->followRedirects(false);

        try {
            $this->session->visit('/admin/');
            $this->session->visit('/admin/orders/');
        } finally {
            $client->followRedirects($following);
        }

        $this->session->visit('/admin/two-factor/setup');
    }

    /**
     * @Then I should be told once to set up two-factor authentication
     */
    public function iShouldBeToldOnceToSetUpTwoFactorAuthentication(): void
    {
        $text = $this->translator->trans('three_brs.two_factor.enforcement_required', [], 'flashes');
        $flashes = array_filter(
            $this->session->getPage()->findAll('css', '[data-test-sylius-flash-message]'),
            static fn (NodeElement $flash): bool => str_contains($flash->getText(), $text),
        );

        Assert::count($flashes, 1, 'Expected the enforcement warning %d time(s), found it %d times.');
    }

    protected function setMode(TwoFactorMode $mode): void
    {
        $this->settingsWriter->set('two_factor_authentication.mode', SettingsScope::ADMIN, $mode->value);
        $this->settingsWriter->flush();
        $this->settingsProvider->refresh();
    }
}
