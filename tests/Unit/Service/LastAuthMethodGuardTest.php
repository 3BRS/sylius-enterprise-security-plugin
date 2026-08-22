<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderRegistryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuard;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasskeyCheckerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

#[CoversClass(LastAuthMethodGuard::class)]
class LastAuthMethodGuardTest extends TestCase
{
    public function testShopUserWithAUsablePasswordCanUnlink(): void
    {
        $guard = $this->makeGuard();

        self::assertTrue($guard->canUnlinkSocialForShopUser($this->shopUser('$argon2i$hashed'), 'google'));
    }

    public function testShopUserWithoutPasswordCannotUnlinkLastLink(): void
    {
        $guard = $this->makeGuard(customerLinks: ['google']);

        self::assertFalse($guard->canUnlinkSocialForShopUser($this->shopUser(null), 'google'));
    }

    public function testShopUserWithoutPasswordCanUnlinkWhenAnotherLinkExists(): void
    {
        $guard = $this->makeGuard(customerLinks: ['google', 'microsoft']);

        self::assertTrue($guard->canUnlinkSocialForShopUser($this->shopUser(null), 'google'));
    }

    /**
     * A link to a provider that is switched off for the scope is not offered on the
     * sign-in page, so it is not a way back into the account either. Counting it let
     * the last working link go and left the customer outside.
     */
    public function testALinkToADisabledProviderIsNoFallback(): void
    {
        $guard = $this->makeGuard(customerLinks: ['google', 'apple'], enabledProviders: ['google']);

        self::assertFalse($guard->canUnlinkSocialForShopUser($this->shopUser(null), 'google'));
    }

    /**
     * The link on its way out may itself be one that never counted, so the check
     * names it instead of subtracting one from the total.
     */
    public function testUnlinkingADisabledProviderLeavesTheEnabledOneCounted(): void
    {
        $guard = $this->makeGuard(customerLinks: ['google', 'apple'], enabledProviders: ['google']);

        self::assertTrue($guard->canUnlinkSocialForShopUser($this->shopUser(null), 'apple'));
    }

    public function testNoProviderEnabledAtAllLeavesNoUsableLink(): void
    {
        $guard = $this->makeGuard(customerLinks: ['google', 'microsoft'], enabledProviders: []);

        self::assertFalse($guard->canUnlinkSocialForShopUser($this->shopUser(null), 'google'));
    }

    public function testShopUserWithoutPasswordCanUnlinkSocialWhenPasskeyExists(): void
    {
        $guard = $this->makeGuard(customerLinks: ['google'], customerPasskeys: 1);

        self::assertTrue($guard->canUnlinkSocialForShopUser($this->shopUser(null), 'google'));
    }

    /**
     * Passkeys answer to the configuration file as well as to Security Settings; a
     * credential the login endpoint refuses is no fallback.
     */
    public function testAPasskeyIsNoFallbackWhileThePasskeyFeatureIsOff(): void
    {
        $guard = $this->makeGuard(customerLinks: ['google'], customerPasskeys: 1, passkeyEnabled: false);

        self::assertFalse($guard->canUnlinkSocialForShopUser($this->shopUser(null), 'google'));
    }

    public function testAdminUserBehavesTheSame(): void
    {
        $guard = $this->makeGuard(adminLinks: ['google']);

        self::assertFalse($guard->canUnlinkSocialForAdminUser($this->adminUser(null), 'google'));
    }

    public function testAdminLinkToADisabledProviderIsNoFallback(): void
    {
        $guard = $this->makeGuard(adminLinks: ['google', 'apple'], enabledProviders: ['google']);

        self::assertFalse($guard->canUnlinkSocialForAdminUser($this->adminUser(null), 'google'));
    }

    public function testShopUserWithoutPasswordCannotRemoveLastPasskeyWithoutSocial(): void
    {
        $guard = $this->makeGuard(customerPasskeys: 1);

        self::assertFalse($guard->canRemovePasskeyForShopUser($this->shopUser(null)));
    }

    public function testShopUserWithoutPasswordCanRemovePasskeyWhenAnotherPasskeyExists(): void
    {
        $guard = $this->makeGuard(customerPasskeys: 2);

        self::assertTrue($guard->canRemovePasskeyForShopUser($this->shopUser(null)));
    }

    public function testShopUserWithoutPasswordCanRemovePasskeyWhenSocialLinkExists(): void
    {
        $guard = $this->makeGuard(customerLinks: ['google'], customerPasskeys: 1);

        self::assertTrue($guard->canRemovePasskeyForShopUser($this->shopUser(null)));
    }

    public function testTheLastPasskeyStaysWhenTheOnlyLinkIsToADisabledProvider(): void
    {
        $guard = $this->makeGuard(customerLinks: ['apple'], customerPasskeys: 1, enabledProviders: ['google']);

        self::assertFalse($guard->canRemovePasskeyForShopUser($this->shopUser(null)));
    }

    public function testAdminPasskeyRemovalBehavesTheSame(): void
    {
        $guard = $this->makeGuard(adminPasskeys: 1);

        self::assertFalse($guard->canRemovePasskeyForAdminUser($this->adminUser(null)));
    }

    public function testAPasswordDoesNotCountWhilePasswordLoginIsOffForTheScope(): void
    {
        $guard = $this->makeGuard(customerLinks: ['google'], passwordLoginEnabled: false);

        self::assertFalse($guard->canUnlinkSocialForShopUser($this->shopUser('$argon2i$hashed'), 'google'));
    }

    public function testAPasswordDoesNotCountForTheLastPasskeyEitherWhilePasswordLoginIsOff(): void
    {
        $guard = $this->makeGuard(customerPasskeys: 1, passwordLoginEnabled: false);

        self::assertFalse($guard->canRemovePasskeyForShopUser($this->shopUser('$argon2i$hashed')));
    }

    public function testAdminPasswordDoesNotCountWhilePasswordLoginIsOffForTheScope(): void
    {
        $guard = $this->makeGuard(adminLinks: ['google'], passwordLoginEnabled: false);

        self::assertFalse($guard->canUnlinkSocialForAdminUser($this->adminUser('$argon2i$hashed'), 'google'));
    }

    /**
     * Unlinking a provider the account never had leaves every method it does have.
     */
    public function testUnlinkingAProviderThatIsNotLinkedIsAllowed(): void
    {
        $guard = $this->makeGuard(customerLinks: ['google']);

        self::assertTrue($guard->canUnlinkSocialForShopUser($this->shopUser(null), 'microsoft'));
    }

    /**
     * @param list<string> $customerLinks
     * @param list<string> $adminLinks
     * @param list<string> $enabledProviders
     */
    protected function makeGuard(
        array $customerLinks = [],
        array $adminLinks = [],
        int $customerPasskeys = 0,
        int $adminPasskeys = 0,
        array $enabledProviders = ['google', 'apple', 'microsoft'],
        bool $passwordLoginEnabled = true,
        bool $passkeyEnabled = true,
    ): LastAuthMethodGuard {
        $customerRepo = $this->createStub(CustomerSocialAccountLinkRepositoryInterface::class);
        $customerRepo->method('findAllByShopUser')->willReturn(
            array_map(fn (string $p) => $this->customerLink($p), $customerLinks),
        );
        $customerRepo->method('findOneByShopUserAndProvider')->willReturnCallback(
            fn (ShopUserInterface $user, string $provider) => in_array($provider, $customerLinks, true)
                ? $this->customerLink($provider)
                : null,
        );

        $adminRepo = $this->createStub(AdminUserSocialAccountLinkRepositoryInterface::class);
        $adminRepo->method('findAllByAdminUser')->willReturn(
            array_map(fn (string $p) => $this->adminLink($p), $adminLinks),
        );
        $adminRepo->method('findOneByAdminUserAndProvider')->willReturnCallback(
            fn (AdminUserInterface $user, string $provider) => in_array($provider, $adminLinks, true)
                ? $this->adminLink($provider)
                : null,
        );

        $customerPasskeyRepo = $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class);
        $customerPasskeyRepo->method('countByShopUser')->willReturn($customerPasskeys);

        $adminPasskeyRepo = $this->createStub(AdminUserPasskeyCredentialRepositoryInterface::class);
        $adminPasskeyRepo->method('countByAdminUser')->willReturn($adminPasskeys);

        $passwordLoginChecker = $this->createStub(PasswordLoginCheckerInterface::class);
        $passwordLoginChecker->method('isEnabled')->willReturn($passwordLoginEnabled);

        $passkeyChecker = $this->createStub(PasskeyCheckerInterface::class);
        $passkeyChecker->method('isEnabled')->willReturn($passkeyEnabled);

        $providers = array_map(fn (string $name) => $this->provider($name), $enabledProviders);
        $registry = $this->createStub(OAuthProviderRegistryInterface::class);
        $registry->method('getEnabledForCustomer')->willReturn($providers);
        $registry->method('getEnabledForAdmin')->willReturn($providers);

        return new LastAuthMethodGuard(
            $customerRepo,
            $adminRepo,
            $customerPasskeyRepo,
            $adminPasskeyRepo,
            $passwordLoginChecker,
            $passkeyChecker,
            $registry,
        );
    }

    protected function shopUser(?string $password): ShopUserInterface
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn($password);

        return $user;
    }

    protected function adminUser(?string $password): AdminUserInterface
    {
        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getPassword')->willReturn($password);

        return $user;
    }

    protected function customerLink(string $provider): CustomerSocialAccountLinkInterface
    {
        $link = $this->createStub(CustomerSocialAccountLinkInterface::class);
        $link->method('getProvider')->willReturn($provider);

        return $link;
    }

    protected function adminLink(string $provider): AdminUserSocialAccountLinkInterface
    {
        $link = $this->createStub(AdminUserSocialAccountLinkInterface::class);
        $link->method('getProvider')->willReturn($provider);

        return $link;
    }

    protected function provider(string $name): OAuthProviderInterface
    {
        $provider = $this->createStub(OAuthProviderInterface::class);
        $provider->method('getName')->willReturn($name);

        return $provider;
    }
}
