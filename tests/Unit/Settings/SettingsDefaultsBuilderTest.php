<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\Configuration;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\Defaults\SettingsDefaultsBuilder;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

#[CoversClass(SettingsDefaultsBuilder::class)]
class SettingsDefaultsBuilderTest extends TestCase
{
    public function testBuildProducesPerScopeMapForAllFeatures(): void
    {
        $config = $this->processedConfig();

        $defaults = (new SettingsDefaultsBuilder())->build($config);

        self::assertArrayHasKey(SettingsScope::CUSTOMER->value, $defaults);
        self::assertArrayHasKey(SettingsScope::ADMIN->value, $defaults);
        self::assertArrayHasKey(SettingsScope::GLOBAL->value, $defaults);

        self::assertSame(8, $defaults[SettingsScope::CUSTOMER->value]['password_policy.min_length']);
        self::assertSame(12, $defaults[SettingsScope::ADMIN->value]['password_policy.min_length']);
        self::assertFalse($defaults[SettingsScope::CUSTOMER->value]['password_history.enabled']);
        self::assertSame('disabled', $defaults[SettingsScope::CUSTOMER->value]['two_factor_authentication.mode']);
        self::assertSame('Sylius', $defaults[SettingsScope::GLOBAL->value]['two_factor_authentication.issuer']);
        self::assertSame(60, $defaults[SettingsScope::GLOBAL->value]['two_factor_authentication.trusted_device.days']);
        self::assertNull($defaults[SettingsScope::GLOBAL->value]['passkey.rp_id']);
    }

    public function testBuildIncludesRateLimitActions(): void
    {
        $config = $this->processedConfig();

        $defaults = (new SettingsDefaultsBuilder())->build($config);

        self::assertSame(5, $defaults[SettingsScope::CUSTOMER->value]['rate_limit.login.limit']);
        self::assertSame('15 minutes', $defaults[SettingsScope::CUSTOMER->value]['rate_limit.login.interval']);
        self::assertSame(3, $defaults[SettingsScope::ADMIN->value]['rate_limit.password_reset.limit']);
    }

    public function testBuildIncludesOauthProviders(): void
    {
        $config = $this->processedConfig();

        $defaults = (new SettingsDefaultsBuilder())->build($config);

        self::assertFalse($defaults[SettingsScope::CUSTOMER->value]['oauth.google.enabled']);
        self::assertNull($defaults[SettingsScope::CUSTOMER->value]['oauth.google.client_id']);
        self::assertSame('en_US', $defaults[SettingsScope::ADMIN->value]['oauth.default_locale']);
        self::assertSame([], $defaults[SettingsScope::ADMIN->value]['oauth.auto_register_allowed_email_domains']);
    }

    /** @return array<string, mixed> */
    private function processedConfig(): array
    {
        $processor = new Processor();

        return $processor->processConfiguration(new Configuration(), [[]]);
    }
}
