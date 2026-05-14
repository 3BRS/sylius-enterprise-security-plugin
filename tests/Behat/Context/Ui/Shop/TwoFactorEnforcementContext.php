<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorMode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\TwoFactorEnforcementChecker;
use Webmozart\Assert\Assert;

class TwoFactorEnforcementContext implements Context
{
    public function __construct(
        private Session $session,
        private TwoFactorEnforcementChecker $enforcementChecker,
    ) {
    }

    /**
     * @BeforeScenario
     */
    public function resetEnforcement(): void
    {
        $this->overrideMode('customerMode', TwoFactorMode::OPTIONAL);
    }

    /**
     * @Given 2FA enforcement is enabled for customers
     */
    public function enforcementIsEnabledForCustomers(): void
    {
        $this->overrideMode('customerMode', TwoFactorMode::ENFORCED);
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

    private function overrideMode(string $property, TwoFactorMode $mode): void
    {
        $reflection = new \ReflectionProperty(TwoFactorEnforcementChecker::class, $property);
        $reflection->setValue($this->enforcementChecker, $mode);
    }
}
