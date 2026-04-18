<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

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
        $reflection = new \ReflectionProperty(TwoFactorEnforcementChecker::class, 'adminMode');
        $reflection->setValue($this->enforcementChecker, TwoFactorMode::OPTIONAL);
    }

    /**
     * @Given 2FA enforcement is enabled for admins
     */
    public function enforcementIsEnabledForAdmins(): void
    {
        $reflection = new \ReflectionProperty(TwoFactorEnforcementChecker::class, 'adminMode');
        $reflection->setValue($this->enforcementChecker, TwoFactorMode::ENFORCED);
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
}
