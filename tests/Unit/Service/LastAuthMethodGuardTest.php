<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderRegistryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
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

    public function testCannotDisablePasswordLoginWhenNoUsableAlternative(): void
    {
        // No magic link, passkey feature off, no OAuth provider enabled.
        $guard = $this->makeGuard(
            featureToggle: $this->featureToggle(),
            oauthRegistry: $this->oauthRegistry(),
        );

        self::assertFalse($guard->canDisablePasswordLoginForShopUser($this->createStub(ShopUserInterface::class)));
    }

    public function testCanDisablePasswordLoginWhenMagicLinkEnabled(): void
    {
        // Magic link needs no per-account credential — enabled = everyone has a way in.
        $guard = $this->makeGuard(featureToggle: $this->featureToggle(magicLink: true));

        self::assertTrue($guard->canDisablePasswordLoginForShopUser($this->createStub(ShopUserInterface::class)));
    }

    public function testCanDisablePasswordLoginWhenPasskeyEnabledAndPresent(): void
    {
        $passkeyRepo = $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class);
        $passkeyRepo->method('countByShopUser')->willReturn(1);

        $guard = $this->makeGuard(
            customerPasskeyRepo: $passkeyRepo,
            featureToggle: $this->featureToggle(passkey: true),
        );

        self::assertTrue($guard->canDisablePasswordLoginForShopUser($this->createStub(ShopUserInterface::class)));
    }

    public function testCannotDisablePasswordLoginWhenPasskeyPresentButFeatureDisabled(): void
    {
        // The user owns a passkey, but passkeys are off in settings — not a usable
        // fallback, so disabling password login would lock them out.
        $passkeyRepo = $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class);
        $passkeyRepo->method('countByShopUser')->willReturn(1);

        $guard = $this->makeGuard(
            customerPasskeyRepo: $passkeyRepo,
            featureToggle: $this->featureToggle(passkey: false),
            oauthRegistry: $this->oauthRegistry(),
        );

        self::assertFalse($guard->canDisablePasswordLoginForShopUser($this->createStub(ShopUserInterface::class)));
    }

    public function testCanDisablePasswordLoginWhenLinkedProviderEnabled(): void
    {
        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('findAllByShopUser')->willReturn([$this->customerLink('google')]);

        $guard = $this->makeGuard(
            $customerRepo,
            featureToggle: $this->featureToggle(),
            oauthRegistry: $this->oauthRegistry('google'),
        );

        self::assertTrue($guard->canDisablePasswordLoginForShopUser($this->createStub(ShopUserInterface::class)));
    }

    public function testCannotDisablePasswordLoginWhenLinkedProviderDisabled(): void
    {
        // The user has a Google link, but Google is disabled (registry reports no
        // enabled provider for the scope) — not a usable fallback.
        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('findAllByShopUser')->willReturn([$this->customerLink('google')]);

        $guard = $this->makeGuard(
            $customerRepo,
            featureToggle: $this->featureToggle(),
            oauthRegistry: $this->oauthRegistry(),
        );

        self::assertFalse($guard->canDisablePasswordLoginForShopUser($this->createStub(ShopUserInterface::class)));
    }

    public function testAdminCannotDisablePasswordLoginWhenNoUsableAlternative(): void
    {
        $guard = $this->makeGuard(
            featureToggle: $this->featureToggle(),
            oauthRegistry: $this->oauthRegistry(),
        );

        self::assertFalse($guard->canDisablePasswordLoginForAdminUser($this->createStub(AdminUserInterface::class)));
    }

    protected function featureToggle(bool $magicLink = false, bool $passkey = false): FeatureToggleInterface
    {
        $featureToggle = $this->createStub(FeatureToggleInterface::class);
        $featureToggle->method('isEnabled')->willReturnCallback(
            static fn (string $feature): bool => match ($feature) {
                'magic_link' => $magicLink,
                'passkey' => $passkey,
                default => false,
            },
        );

        return $featureToggle;
    }

    protected function oauthRegistry(string ...$enabledProviderNames): OAuthProviderRegistryInterface
    {
        $providers = array_map(
            function (string $name): OAuthProviderInterface {
                $provider = $this->createStub(OAuthProviderInterface::class);
                $provider->method('getName')->willReturn($name);

                return $provider;
            },
            $enabledProviderNames,
        );

        $registry = $this->createStub(OAuthProviderRegistryInterface::class);
        $registry->method('getEnabledForCustomer')->willReturn($providers);
        $registry->method('getEnabledForAdmin')->willReturn($providers);

        return $registry;
    }

    protected function customerLink(string $provider): CustomerSocialAccountLinkInterface
    {
        $link = $this->createStub(CustomerSocialAccountLinkInterface::class);
        $link->method('getProvider')->willReturn($provider);

        return $link;
    }

    protected function makeGuard(
        ?CustomerSocialAccountLinkRepositoryInterface $customerRepo = null,
        ?AdminUserSocialAccountLinkRepositoryInterface $adminRepo = null,
        ?CustomerPasskeyCredentialRepositoryInterface $customerPasskeyRepo = null,
        ?AdminUserPasskeyCredentialRepositoryInterface $adminPasskeyRepo = null,
        ?FeatureToggleInterface $featureToggle = null,
        ?OAuthProviderRegistryInterface $oauthRegistry = null,
    ): LastAuthMethodGuard {
        return new LastAuthMethodGuard(
            $customerRepo ?? $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class),
            $adminRepo ?? $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class),
            $customerPasskeyRepo ?? $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class),
            $adminPasskeyRepo ?? $this->createStub(AdminUserPasskeyCredentialRepositoryInterface::class),
            $featureToggle ?? $this->createStub(FeatureToggleInterface::class),
            $oauthRegistry ?? $this->createStub(OAuthProviderRegistryInterface::class),
        );
    }
}
