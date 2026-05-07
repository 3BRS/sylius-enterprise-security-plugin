<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\FeatureToggle;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

#[CoversClass(FeatureToggle::class)]
class FeatureToggleTest extends TestCase
{
    public function testReadsEnabledKeyForGivenFeatureAndScope(): void
    {
        $provider = $this->createMock(SettingsProviderInterface::class);
        $provider->expects(self::once())
            ->method('getBool')
            ->with('passkey.enabled', SettingsScope::CUSTOMER)
            ->willReturn(true);

        $toggle = new FeatureToggle($provider);

        self::assertTrue($toggle->isEnabled('passkey', SettingsScope::CUSTOMER));
    }

    public function testTwoFactorIsInactiveWhenModeIsDisabled(): void
    {
        $provider = $this->createMock(SettingsProviderInterface::class);
        $provider->expects(self::once())
            ->method('getString')
            ->with('two_factor_authentication.mode', SettingsScope::CUSTOMER)
            ->willReturn('disabled');

        $toggle = new FeatureToggle($provider);

        self::assertFalse($toggle->isTwoFactorActive(SettingsScope::CUSTOMER));
    }

    public function testTwoFactorIsActiveWhenModeIsOptional(): void
    {
        $provider = $this->createMock(SettingsProviderInterface::class);
        $provider->expects(self::once())
            ->method('getString')
            ->with('two_factor_authentication.mode', SettingsScope::ADMIN)
            ->willReturn('optional');

        $toggle = new FeatureToggle($provider);

        self::assertTrue($toggle->isTwoFactorActive(SettingsScope::ADMIN));
    }

    public function testTwoFactorIsActiveWhenModeIsEnforced(): void
    {
        $provider = $this->createMock(SettingsProviderInterface::class);
        $provider->expects(self::once())
            ->method('getString')
            ->with('two_factor_authentication.mode', SettingsScope::CUSTOMER)
            ->willReturn('enforced');

        $toggle = new FeatureToggle($provider);

        self::assertTrue($toggle->isTwoFactorActive(SettingsScope::CUSTOMER));
    }
}
