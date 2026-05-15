<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
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

    protected function setMode(TwoFactorMode $mode): void
    {
        $this->settingsWriter->set('two_factor_authentication.mode', SettingsScope::ADMIN, $mode->value);
        $this->settingsWriter->flush();
        $this->settingsProvider->refresh();
    }
}
