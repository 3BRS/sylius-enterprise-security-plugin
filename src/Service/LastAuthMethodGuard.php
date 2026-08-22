<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderRegistryInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\SocialAccountLinkRecordInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;

class LastAuthMethodGuard implements LastAuthMethodGuardInterface
{
    public function __construct(
        protected CustomerSocialAccountLinkRepositoryInterface $customerLinkRepository,
        protected AdminUserSocialAccountLinkRepositoryInterface $adminLinkRepository,
        protected CustomerPasskeyCredentialRepositoryInterface $customerPasskeyRepository,
        protected AdminUserPasskeyCredentialRepositoryInterface $adminPasskeyRepository,
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
        protected ScopedFeatureCheckerInterface $passkeyChecker,
        protected OAuthProviderRegistryInterface $oauthRegistry,
    ) {
    }

    public function canUnlinkSocialForShopUser(ShopUserInterface $user, string $provider): bool
    {
        if ($this->hasUsablePassword($user->getPassword(), SettingsScope::CUSTOMER)) {
            return true;
        }

        $link = $this->customerLinkRepository->findOneByShopUserAndProvider($user, $provider);
        if ($link === null) {
            return true;
        }

        $remainingSocial = $this->countUsableLinks(
            $this->customerLinkRepository->findAllByShopUser($user),
            $this->oauthRegistry->getEnabledForCustomer(),
            $provider,
        );
        $passkeys = $this->countUsablePasskeysForShopUser($user);

        return ($remainingSocial + $passkeys) >= 1;
    }

    public function canUnlinkSocialForAdminUser(AdminUserInterface $user, string $provider): bool
    {
        if ($this->hasUsablePassword($user->getPassword(), SettingsScope::ADMIN)) {
            return true;
        }

        $link = $this->adminLinkRepository->findOneByAdminUserAndProvider($user, $provider);
        if ($link === null) {
            return true;
        }

        $remainingSocial = $this->countUsableLinks(
            $this->adminLinkRepository->findAllByAdminUser($user),
            $this->oauthRegistry->getEnabledForAdmin(),
            $provider,
        );
        $passkeys = $this->countUsablePasskeysForAdminUser($user);

        return ($remainingSocial + $passkeys) >= 1;
    }

    public function canRemovePasskeyForShopUser(ShopUserInterface $user): bool
    {
        if ($this->hasUsablePassword($user->getPassword(), SettingsScope::CUSTOMER)) {
            return true;
        }

        $remainingPasskeys = max(0, $this->countUsablePasskeysForShopUser($user) - 1);
        $socialLinks = $this->countUsableLinks(
            $this->customerLinkRepository->findAllByShopUser($user),
            $this->oauthRegistry->getEnabledForCustomer(),
        );

        return ($remainingPasskeys + $socialLinks) >= 1;
    }

    public function canRemovePasskeyForAdminUser(AdminUserInterface $user): bool
    {
        if ($this->hasUsablePassword($user->getPassword(), SettingsScope::ADMIN)) {
            return true;
        }

        $remainingPasskeys = max(0, $this->countUsablePasskeysForAdminUser($user) - 1);
        $socialLinks = $this->countUsableLinks(
            $this->adminLinkRepository->findAllByAdminUser($user),
            $this->oauthRegistry->getEnabledForAdmin(),
        );

        return ($remainingPasskeys + $socialLinks) >= 1;
    }

    /**
     * A link is a way back in only while its provider is switched on for the
     * scope. A provider can be turned off in Security Settings, or lose its
     * credentials in the configuration, and either way the sign-in page stops
     * offering it — the stored link stays behind and opens nothing.
     *
     * The provider being unlinked is named rather than subtracted, because the
     * one on its way out may itself be a disabled provider that never counted.
     *
     * @param list<SocialAccountLinkRecordInterface> $links
     * @param list<OAuthProviderInterface>           $enabledProviders
     */
    protected function countUsableLinks(array $links, array $enabledProviders, ?string $excludedProvider = null): int
    {
        $enabledNames = array_map(
            static fn (OAuthProviderInterface $provider): string => $provider->getName(),
            $enabledProviders,
        );

        $usable = 0;
        foreach ($links as $link) {
            $name = $link->getProvider();
            if ($name === $excludedProvider) {
                continue;
            }

            if (in_array($name, $enabledNames, true)) {
                ++$usable;
            }
        }

        return $usable;
    }

    protected function countUsablePasskeysForShopUser(ShopUserInterface $user): int
    {
        if (!$this->passkeyChecker->isEnabled(SettingsScope::CUSTOMER)) {
            return 0;
        }

        return $this->customerPasskeyRepository->countByShopUser($user);
    }

    protected function countUsablePasskeysForAdminUser(AdminUserInterface $user): int
    {
        if (!$this->passkeyChecker->isEnabled(SettingsScope::ADMIN)) {
            return 0;
        }

        return $this->adminPasskeyRepository->countByAdminUser($user);
    }

    /**
     * A stored hash is only a way back in while the scope still accepts passwords.
     * With password login switched off, AbstractPasswordLoginCheckListener refuses
     * the credential whatever else the account has, so treating the hash as a
     * fallback here would let the owner of a single social link or a single passkey
     * remove the only method that still works.
     *
     * The API firewalls are deliberately exempt from that switch, so an account
     * whose password still opens /api/v2 is nonetheless held to this rule — the
     * guard exists to keep people out of the web panel, which is what the switch
     * closes.
     */
    protected function hasUsablePassword(?string $password, SettingsScope $scope): bool
    {
        if (!$this->passwordLoginChecker->isEnabled($scope)) {
            return false;
        }

        return $password !== null && $password !== '';
    }
}
