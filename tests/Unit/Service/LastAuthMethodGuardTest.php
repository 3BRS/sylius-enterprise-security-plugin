<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuard;

#[CoversClass(LastAuthMethodGuard::class)]
class LastAuthMethodGuardTest extends TestCase
{
    public function testShopUserWithPasswordCanAlwaysUnlink(): void
    {
        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $adminRepo = $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class);

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn('$argon2i$hashed');

        $guard = new LastAuthMethodGuard($customerRepo, $adminRepo);

        self::assertTrue($guard->canUnlinkSocialForShopUser($user, 'google'));
    }

    public function testShopUserWithoutPasswordCannotUnlinkLastLink(): void
    {
        $link = $this->createStub(CustomerSocialAccountLinkInterface::class);
        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('countByShopUser')->willReturn(1);
        $customerRepo->method('findOneByShopUserAndProvider')->willReturn($link);

        $adminRepo = $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class);

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = new LastAuthMethodGuard($customerRepo, $adminRepo);

        self::assertFalse($guard->canUnlinkSocialForShopUser($user, 'google'));
    }

    public function testShopUserWithoutPasswordCanUnlinkWhenAnotherLinkExists(): void
    {
        $link = $this->createStub(CustomerSocialAccountLinkInterface::class);
        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('countByShopUser')->willReturn(2);
        $customerRepo->method('findOneByShopUserAndProvider')->willReturn($link);

        $adminRepo = $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class);

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = new LastAuthMethodGuard($customerRepo, $adminRepo);

        self::assertTrue($guard->canUnlinkSocialForShopUser($user, 'google'));
    }

    public function testAdminUserBehavesTheSame(): void
    {
        $link = $this->createStub(AdminUserSocialAccountLinkInterface::class);
        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $adminRepo = $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class);
        $adminRepo->method('countByAdminUser')->willReturn(1);
        $adminRepo->method('findOneByAdminUserAndProvider')->willReturn($link);

        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = new LastAuthMethodGuard($customerRepo, $adminRepo);

        self::assertFalse($guard->canUnlinkSocialForAdminUser($user, 'apple'));
    }
}
