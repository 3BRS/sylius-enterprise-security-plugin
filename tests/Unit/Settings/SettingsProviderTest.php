<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySetting;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\SecuritySettingRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\Defaults\SettingsDefaultsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProvider;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

#[CoversClass(SettingsProvider::class)]
class SettingsProviderTest extends TestCase
{
    public function testReturnsValueFromDatabaseWhenPresent(): void
    {
        $setting = new SecuritySetting();
        $setting->setPath('password_policy.min_length');
        $setting->setScope(SettingsScope::CUSTOMER->value);
        $setting->setValue(14);

        $repository = $this->createStub(SecuritySettingRepositoryInterface::class);
        $repository->method('findAllSettings')->willReturn([$setting]);

        $defaults = $this->createStub(SettingsDefaultsProviderInterface::class);

        $provider = new SettingsProvider($repository, $defaults);

        self::assertSame(14, $provider->getInt('password_policy.min_length', SettingsScope::CUSTOMER));
    }

    public function testFallsBackToDefaultsWhenNotInDatabase(): void
    {
        $repository = $this->createStub(SecuritySettingRepositoryInterface::class);
        $repository->method('findAllSettings')->willReturn([]);

        $defaults = $this->createStub(SettingsDefaultsProviderInterface::class);
        $defaults->method('get')->willReturnCallback(static function (string $path, SettingsScope $scope): mixed {
            if ($path === 'password_policy.min_length' && $scope === SettingsScope::CUSTOMER) {
                return 8;
            }

            return null;
        });

        $provider = new SettingsProvider($repository, $defaults);

        self::assertSame(8, $provider->getInt('password_policy.min_length', SettingsScope::CUSTOMER));
    }

    public function testCustomerAndAdminScopesAreIsolated(): void
    {
        $customerSetting = new SecuritySetting();
        $customerSetting->setPath('password_policy.min_length');
        $customerSetting->setScope(SettingsScope::CUSTOMER->value);
        $customerSetting->setValue(8);

        $adminSetting = new SecuritySetting();
        $adminSetting->setPath('password_policy.min_length');
        $adminSetting->setScope(SettingsScope::ADMIN->value);
        $adminSetting->setValue(20);

        $repository = $this->createStub(SecuritySettingRepositoryInterface::class);
        $repository->method('findAllSettings')->willReturn([$customerSetting, $adminSetting]);

        $defaults = $this->createStub(SettingsDefaultsProviderInterface::class);

        $provider = new SettingsProvider($repository, $defaults);

        self::assertSame(8, $provider->getInt('password_policy.min_length', SettingsScope::CUSTOMER));
        self::assertSame(20, $provider->getInt('password_policy.min_length', SettingsScope::ADMIN));
    }

    public function testGetBoolCoercesNonBooleanValues(): void
    {
        $setting = new SecuritySetting();
        $setting->setPath('feature.enabled');
        $setting->setScope(SettingsScope::CUSTOMER->value);
        $setting->setValue(0);

        $repository = $this->createStub(SecuritySettingRepositoryInterface::class);
        $repository->method('findAllSettings')->willReturn([$setting]);

        $defaults = $this->createStub(SettingsDefaultsProviderInterface::class);

        $provider = new SettingsProvider($repository, $defaults);

        self::assertFalse($provider->getBool('feature.enabled', SettingsScope::CUSTOMER));
    }

    public function testGetNullableIntReturnsNullForNullValue(): void
    {
        $setting = new SecuritySetting();
        $setting->setPath('account_lockout.auto_unlock_after');
        $setting->setScope(SettingsScope::CUSTOMER->value);
        $setting->setValue(null);

        $repository = $this->createStub(SecuritySettingRepositoryInterface::class);
        $repository->method('findAllSettings')->willReturn([$setting]);

        $defaults = $this->createStub(SettingsDefaultsProviderInterface::class);

        $provider = new SettingsProvider($repository, $defaults);

        self::assertNull($provider->getNullableInt('account_lockout.auto_unlock_after', SettingsScope::CUSTOMER));
    }

    public function testGetStringListReturnsArrayOfStrings(): void
    {
        $setting = new SecuritySetting();
        $setting->setPath('oauth.auto_register_allowed_email_domains');
        $setting->setScope(SettingsScope::ADMIN->value);
        $setting->setValue(['example.com', 'company.io']);

        $repository = $this->createStub(SecuritySettingRepositoryInterface::class);
        $repository->method('findAllSettings')->willReturn([$setting]);

        $defaults = $this->createStub(SettingsDefaultsProviderInterface::class);

        $provider = new SettingsProvider($repository, $defaults);

        self::assertSame(
            ['example.com', 'company.io'],
            $provider->getStringList('oauth.auto_register_allowed_email_domains', SettingsScope::ADMIN),
        );
    }

    public function testRefreshClearsCache(): void
    {
        $setting = new SecuritySetting();
        $setting->setPath('password_policy.min_length');
        $setting->setScope(SettingsScope::CUSTOMER->value);
        $setting->setValue(8);

        $repository = $this->createMock(SecuritySettingRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('findAllSettings')->willReturn([$setting]);

        $defaults = $this->createStub(SettingsDefaultsProviderInterface::class);

        $provider = new SettingsProvider($repository, $defaults);
        $provider->getInt('password_policy.min_length', SettingsScope::CUSTOMER);
        $provider->refresh();
        $provider->getInt('password_policy.min_length', SettingsScope::CUSTOMER);
    }

    public function testCachesRepositoryReadAcrossMultipleQueries(): void
    {
        $repository = $this->createMock(SecuritySettingRepositoryInterface::class);
        $repository->expects(self::once())->method('findAllSettings')->willReturn([]);

        $defaults = $this->createStub(SettingsDefaultsProviderInterface::class);
        $defaults->method('get')->willReturn(0);

        $provider = new SettingsProvider($repository, $defaults);
        $provider->getInt('a', SettingsScope::CUSTOMER);
        $provider->getInt('b', SettingsScope::CUSTOMER);
        $provider->getInt('c', SettingsScope::ADMIN);
    }
}
