<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\Defaults\YamlConfigDefaultsProvider;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

#[CoversClass(YamlConfigDefaultsProvider::class)]
class YamlConfigDefaultsProviderTest extends TestCase
{
    public function testGetReturnsValueWhenPresent(): void
    {
        $provider = new YamlConfigDefaultsProvider([
            'customer' => ['password_policy.min_length' => 8],
            'admin' => [],
            'global' => [],
        ]);

        self::assertSame(8, $provider->get('password_policy.min_length', SettingsScope::CUSTOMER));
    }

    public function testGetReturnsNullWhenAbsent(): void
    {
        $provider = new YamlConfigDefaultsProvider([
            'customer' => [],
            'admin' => [],
            'global' => [],
        ]);

        self::assertNull($provider->get('does.not.exist', SettingsScope::CUSTOMER));
    }

    public function testHasIsTrueOnlyWhenKeyExists(): void
    {
        $provider = new YamlConfigDefaultsProvider([
            'customer' => ['x' => null],
            'admin' => [],
            'global' => [],
        ]);

        self::assertTrue($provider->has('x', SettingsScope::CUSTOMER));
        self::assertFalse($provider->has('y', SettingsScope::CUSTOMER));
        self::assertFalse($provider->has('x', SettingsScope::ADMIN));
    }

    public function testAllReturnsRawMap(): void
    {
        $defaults = [
            'customer' => ['x' => 1],
            'admin' => ['y' => 2],
            'global' => ['z' => 3],
        ];

        $provider = new YamlConfigDefaultsProvider($defaults);

        self::assertSame($defaults, $provider->all());
    }
}
