<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorMode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\TwoFactorEnforcementChecker;

#[CoversClass(TwoFactorEnforcementChecker::class)]
class TwoFactorEnforcementCheckerTest extends TestCase
{
    public function testShopUserNotEnforcedWhenModeIsDisabled(): void
    {
        $checker = new TwoFactorEnforcementChecker(TwoFactorMode::DISABLED, TwoFactorMode::ENFORCED);

        self::assertFalse($checker->shouldEnforceForShopUser($this->shopUser(twoFactorEnabled: false)));
    }

    public function testShopUserNotEnforcedWhenModeIsOptional(): void
    {
        $checker = new TwoFactorEnforcementChecker(TwoFactorMode::OPTIONAL, TwoFactorMode::ENFORCED);

        self::assertFalse($checker->shouldEnforceForShopUser($this->shopUser(twoFactorEnabled: false)));
    }

    public function testShopUserEnforcedWhenModeIsEnforcedAndNotYetEnabled(): void
    {
        $checker = new TwoFactorEnforcementChecker(TwoFactorMode::ENFORCED, TwoFactorMode::DISABLED);

        self::assertTrue($checker->shouldEnforceForShopUser($this->shopUser(twoFactorEnabled: false)));
    }

    public function testShopUserNotEnforcedWhenAlreadyEnabled(): void
    {
        $checker = new TwoFactorEnforcementChecker(TwoFactorMode::ENFORCED, TwoFactorMode::DISABLED);

        self::assertFalse($checker->shouldEnforceForShopUser($this->shopUser(twoFactorEnabled: true)));
    }

    public function testAdminUserEnforcedWhenModeIsEnforcedAndNotYetEnabled(): void
    {
        $checker = new TwoFactorEnforcementChecker(TwoFactorMode::DISABLED, TwoFactorMode::ENFORCED);

        self::assertTrue($checker->shouldEnforceForAdminUser($this->adminUser(twoFactorEnabled: false)));
    }

    public function testAdminUserNotEnforcedWhenAlreadyEnabled(): void
    {
        $checker = new TwoFactorEnforcementChecker(TwoFactorMode::DISABLED, TwoFactorMode::ENFORCED);

        self::assertFalse($checker->shouldEnforceForAdminUser($this->adminUser(twoFactorEnabled: true)));
    }

    public function testAdminAndCustomerModesAreIndependent(): void
    {
        $checker = new TwoFactorEnforcementChecker(TwoFactorMode::DISABLED, TwoFactorMode::ENFORCED);

        self::assertFalse($checker->shouldEnforceForShopUser($this->shopUser(twoFactorEnabled: false)));
        self::assertTrue($checker->shouldEnforceForAdminUser($this->adminUser(twoFactorEnabled: false)));
    }

    private function shopUser(bool $twoFactorEnabled): ShopUserInterface&TwoFactorAuthShopUserInterface
    {
        $user = $this->createStub(CombinedShopUser::class);
        $user->method('isTwoFactorEnabled')->willReturn($twoFactorEnabled);

        return $user;
    }

    private function adminUser(bool $twoFactorEnabled): AdminUserInterface&TwoFactorAuthAdminUserInterface
    {
        $user = $this->createStub(CombinedAdminUser::class);
        $user->method('isTwoFactorEnabled')->willReturn($twoFactorEnabled);

        return $user;
    }
}

interface CombinedShopUser extends ShopUserInterface, TwoFactorAuthShopUserInterface
{
}

interface CombinedAdminUser extends AdminUserInterface, TwoFactorAuthAdminUserInterface
{
}
