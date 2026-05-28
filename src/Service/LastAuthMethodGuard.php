<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderRegistryInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\SocialAccountLinkRecordInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
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
        protected FeatureToggleInterface $featureToggle,
        protected OAuthProviderRegistryInterface $oauthRegistry,
    ) {
    }

    public function canUnlinkSocialForShopUser(ShopUserInterface $user, string $provider): bool
    {
        if ($this->hasUsablePassword($user->getPassword())) {
            return true;
        }

        $link = $this->customerLinkRepository->findOneByShopUserAndProvider($user, $provider);
        if ($link === null) {
            return true;
        }

        $remainingSocial = $this->customerLinkRepository->countByShopUser($user) - 1;
        $passkeys = $this->customerPasskeyRepository->countByShopUser($user);

        return ($remainingSocial + $passkeys) >= 1;
    }

    public function canUnlinkSocialForAdminUser(AdminUserInterface $user, string $provider): bool
    {
        if ($this->hasUsablePassword($user->getPassword())) {
            return true;
        }

        $link = $this->adminLinkRepository->findOneByAdminUserAndProvider($user, $provider);
        if ($link === null) {
            return true;
        }

        $remainingSocial = $this->adminLinkRepository->countByAdminUser($user) - 1;
        $passkeys = $this->adminPasskeyRepository->countByAdminUser($user);

        return ($remainingSocial + $passkeys) >= 1;
    }

    public function canRemovePasskeyForShopUser(ShopUserInterface $user): bool
    {
        if ($this->hasUsablePassword($user->getPassword())) {
            return true;
        }

        $remainingPasskeys = $this->customerPasskeyRepository->countByShopUser($user) - 1;
        $socialLinks = $this->customerLinkRepository->countByShopUser($user);

        return ($remainingPasskeys + $socialLinks) >= 1;
    }

    public function canRemovePasskeyForAdminUser(AdminUserInterface $user): bool
    {
        if ($this->hasUsablePassword($user->getPassword())) {
            return true;
        }

        $remainingPasskeys = $this->adminPasskeyRepository->countByAdminUser($user) - 1;
        $socialLinks = $this->adminLinkRepository->countByAdminUser($user);

        return ($remainingPasskeys + $socialLinks) >= 1;
    }

    public function canDisablePasswordLoginForShopUser(ShopUserInterface $user): bool
    {
        // Magic link needs no per-account credential — if it is enabled for the
        // scope, every user already has a way in via their email address.
        if ($this->featureToggle->isEnabled('magic_link', SettingsScope::CUSTOMER)) {
            return true;
        }

        // A passkey / OAuth link only counts as a fallback when that method is
        // also enabled in settings — a credential the user cannot actually use
        // to sign in (feature off, or the linked provider disabled) is no fallback.
        if ($this->featureToggle->isEnabled('passkey', SettingsScope::CUSTOMER) &&
            $this->customerPasskeyRepository->countByShopUser($user) > 0) {
            return true;
        }

        return $this->hasUsableOAuthLink(
            $this->customerLinkRepository->findAllByShopUser($user),
            $this->oauthRegistry->getEnabledForCustomer(),
        );
    }

    public function canDisablePasswordLoginForAdminUser(AdminUserInterface $user): bool
    {
        if ($this->featureToggle->isEnabled('magic_link', SettingsScope::ADMIN)) {
            return true;
        }

        if ($this->featureToggle->isEnabled('passkey', SettingsScope::ADMIN) &&
            $this->adminPasskeyRepository->countByAdminUser($user) > 0) {
            return true;
        }

        return $this->hasUsableOAuthLink(
            $this->adminLinkRepository->findAllByAdminUser($user),
            $this->oauthRegistry->getEnabledForAdmin(),
        );
    }

    /**
     * True when the user has at least one social link to a provider that is
     * currently enabled for the relevant scope.
     *
     * @param list<SocialAccountLinkRecordInterface> $links
     * @param list<OAuthProviderInterface>           $enabledProviders
     */
    protected function hasUsableOAuthLink(array $links, array $enabledProviders): bool
    {
        $enabledNames = array_map(
            static fn (OAuthProviderInterface $provider): string => $provider->getName(),
            $enabledProviders,
        );

        if ($enabledNames === []) {
            return false;
        }

        foreach ($links as $link) {
            if (in_array($link->getProvider(), $enabledNames, true)) {
                return true;
            }
        }

        return false;
    }

    protected function hasUsablePassword(?string $password): bool
    {
        return $password !== null && $password !== '';
    }
}
