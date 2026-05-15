<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorMode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\PolicyFactory;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

#[CoversClass(PolicyFactory::class)]
class PolicyFactoryTest extends TestCase
{
    public function testPasswordPolicyReadsAllFieldsForScope(): void
    {
        $provider = $this->createStub(SettingsProviderInterface::class);
        $provider->method('getInt')->willReturnCallback(static fn (string $path): int => match ($path) {
            'password_policy.min_length' => 14,
            default => 0,
        });
        $provider->method('getNullableInt')->willReturn(null);
        $provider->method('getBool')->willReturnCallback(static fn (string $path): bool => match ($path) {
            'password_policy.require_uppercase' => true,
            'password_policy.require_special_characters' => true,
            default => false,
        });

        $factory = new PolicyFactory($provider);
        $policy = $factory->passwordPolicy(SettingsScope::CUSTOMER);

        self::assertSame(14, $policy->getMinLength());
        self::assertNull($policy->getMaxLength());
        self::assertTrue($policy->isRequireUppercase());
        self::assertFalse($policy->isRequireLowercase());
        self::assertFalse($policy->isRequireNumbers());
        self::assertTrue($policy->isRequireSpecialCharacters());
    }

    public function testLockoutPolicyReadsAllFieldsForScope(): void
    {
        $provider = $this->createStub(SettingsProviderInterface::class);
        $provider->method('getBool')->willReturn(true);
        $provider->method('getInt')->willReturn(5);
        $provider->method('getNullableInt')->willReturn(900);

        $factory = new PolicyFactory($provider);
        $policy = $factory->lockoutPolicy(SettingsScope::ADMIN);

        self::assertTrue($policy->isEnabled());
        self::assertSame(5, $policy->getMaxAttempts());
        self::assertSame(900, $policy->getAutoUnlockAfter());
    }

    public function testTwoFactorModeReturnsEnumFromString(): void
    {
        $provider = $this->createStub(SettingsProviderInterface::class);
        $provider->method('getString')->willReturn('enforced');

        $factory = new PolicyFactory($provider);
        $mode = $factory->twoFactorMode(SettingsScope::ADMIN);

        self::assertSame(TwoFactorMode::ENFORCED, $mode);
    }
}
