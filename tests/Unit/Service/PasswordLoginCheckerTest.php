<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginChecker;

#[CoversClass(PasswordLoginChecker::class)]
class PasswordLoginCheckerTest extends TestCase
{
    public function testReturnsTheFeatureToggleValueForTheScope(): void
    {
        $featureToggle = $this->createStub(FeatureToggleInterface::class);
        $featureToggle->method('isEnabled')->willReturnMap([
            ['password_login', SettingsScope::CUSTOMER, true],
            ['password_login', SettingsScope::ADMIN, false],
        ]);

        $checker = new PasswordLoginChecker($featureToggle);

        self::assertTrue($checker->isEnabled(SettingsScope::CUSTOMER));
        self::assertFalse($checker->isEnabled(SettingsScope::ADMIN));
    }
}
