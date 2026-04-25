<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuard;

#[CoversClass(LastAuthMethodGuard::class)]
class LastAuthMethodGuardTest extends TestCase
{
    public function testShopUserWithPasswordCanAlwaysUnlink(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn('$argon2i$hashed');

        $guard = $this->makeGuard();

        self::assertTrue($guard->canUnlinkSocialForShopUser($user, 'google'));
    }

    public function testShopUserWithoutPasswordCannotUnlinkLastLink(): void
    {
        $link = $this->createStub(CustomerSocialAccountLinkInterface::class);
        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('countByShopUser')->willReturn(1);
        $customerRepo->method('findOneByShopUserAndProvider')->willReturn($link);

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = $this->makeGuard($customerRepo);

        self::assertFalse($guard->canUnlinkSocialForShopUser($user, 'google'));
    }

    public function testShopUserWithoutPasswordCanUnlinkWhenAnotherLinkExists(): void
    {
        $link = $this->createStub(CustomerSocialAccountLinkInterface::class);
        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('countByShopUser')->willReturn(2);
        $customerRepo->method('findOneByShopUserAndProvider')->willReturn($link);

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = $this->makeGuard($customerRepo);

        self::assertTrue($guard->canUnlinkSocialForShopUser($user, 'google'));
    }

    public function testShopUserWithoutPasswordCanUnlinkSocialWhenPasskeyExists(): void
    {
        $link = $this->createStub(CustomerSocialAccountLinkInterface::class);
        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('countByShopUser')->willReturn(1);
        $customerRepo->method('findOneByShopUserAndProvider')->willReturn($link);

        $passkeyRepo = $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class);
        $passkeyRepo->method('countByShopUser')->willReturn(1);

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = $this->makeGuard($customerRepo, null, $passkeyRepo);

        self::assertTrue($guard->canUnlinkSocialForShopUser($user, 'google'));
    }

    public function testAdminUserBehavesTheSame(): void
    {
        $link = $this->createStub(AdminUserSocialAccountLinkInterface::class);
        $adminRepo = $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class);
        $adminRepo->method('countByAdminUser')->willReturn(1);
        $adminRepo->method('findOneByAdminUserAndProvider')->willReturn($link);

        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = $this->makeGuard(null, $adminRepo);

        self::assertFalse($guard->canUnlinkSocialForAdminUser($user, 'apple'));
    }

    public function testShopUserWithoutPasswordCannotRemoveLastPasskeyWithoutSocial(): void
    {
        $passkeyRepo = $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class);
        $passkeyRepo->method('countByShopUser')->willReturn(1);

        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('countByShopUser')->willReturn(0);

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = $this->makeGuard($customerRepo, null, $passkeyRepo);

        self::assertFalse($guard->canRemovePasskeyForShopUser($user));
    }

    public function testShopUserWithoutPasswordCanRemovePasskeyWhenAnotherPasskeyExists(): void
    {
        $passkeyRepo = $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class);
        $passkeyRepo->method('countByShopUser')->willReturn(2);

        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('countByShopUser')->willReturn(0);

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = $this->makeGuard($customerRepo, null, $passkeyRepo);

        self::assertTrue($guard->canRemovePasskeyForShopUser($user));
    }

    public function testShopUserWithoutPasswordCanRemovePasskeyWhenSocialLinkExists(): void
    {
        $passkeyRepo = $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class);
        $passkeyRepo->method('countByShopUser')->willReturn(1);

        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('countByShopUser')->willReturn(1);

        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = $this->makeGuard($customerRepo, null, $passkeyRepo);

        self::assertTrue($guard->canRemovePasskeyForShopUser($user));
    }

    public function testAdminPasskeyRemovalBehavesTheSame(): void
    {
        $passkeyRepo = $this->createStub(AdminUserPasskeyCredentialRepositoryInterface::class);
        $passkeyRepo->method('countByAdminUser')->willReturn(1);

        $adminRepo = $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class);
        $adminRepo->method('countByAdminUser')->willReturn(0);

        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $guard = $this->makeGuard(null, $adminRepo, null, $passkeyRepo);

        self::assertFalse($guard->canRemovePasskeyForAdminUser($user));
    }

    protected function makeGuard(
        ?CustomerSocialAccountLinkRepositoryInterface $customerRepo = null,
        ?AdminUserSocialAccountLinkRepositoryInterface $adminRepo = null,
        ?CustomerPasskeyCredentialRepositoryInterface $customerPasskeyRepo = null,
        ?AdminUserPasskeyCredentialRepositoryInterface $adminPasskeyRepo = null,
    ): LastAuthMethodGuard {
        return new LastAuthMethodGuard(
            $customerRepo ?? $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class),
            $adminRepo ?? $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class),
            $customerPasskeyRepo ?? $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class),
            $adminPasskeyRepo ?? $this->createStub(AdminUserPasskeyCredentialRepositoryInterface::class),
        );
    }
}
