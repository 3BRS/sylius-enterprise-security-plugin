<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

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
     * @Given 2FA enforcement is enabled for customers
     */
    public function enforcementIsEnabledForCustomers(): void
    {
        $this->setMode(TwoFactorMode::ENFORCED);
    }

    /**
     * @When I visit the account dashboard
     */
    public function iVisitTheAccountDashboard(): void
    {
        $this->session->visit('/en_US/account/dashboard');
    }

    /**
     * @Then I should be redirected to the 2FA setup page
     */
    public function iShouldBeRedirectedToTheSetupPage(): void
    {
        Assert::contains($this->session->getCurrentUrl(), '/account/two-factor/setup');
    }

    /**
     * @Then I should remain on the account dashboard
     */
    public function iShouldRemainOnTheAccountDashboard(): void
    {
        Assert::contains($this->session->getCurrentUrl(), '/account/dashboard');
    }

    protected function setMode(TwoFactorMode $mode): void
    {
        $this->settingsWriter->set('two_factor_authentication.mode', SettingsScope::CUSTOMER, $mode->value);
        $this->settingsWriter->flush();
        $this->settingsProvider->refresh();
    }
}
