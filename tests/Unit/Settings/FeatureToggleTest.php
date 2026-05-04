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
}
