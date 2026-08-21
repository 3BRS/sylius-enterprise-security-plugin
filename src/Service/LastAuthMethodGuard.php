<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
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

        $remainingSocial = $this->customerLinkRepository->countByShopUser($user) - 1;
        $passkeys = $this->customerPasskeyRepository->countByShopUser($user);

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

        $remainingSocial = $this->adminLinkRepository->countByAdminUser($user) - 1;
        $passkeys = $this->adminPasskeyRepository->countByAdminUser($user);

        return ($remainingSocial + $passkeys) >= 1;
    }

    public function canRemovePasskeyForShopUser(ShopUserInterface $user): bool
    {
        if ($this->hasUsablePassword($user->getPassword(), SettingsScope::CUSTOMER)) {
            return true;
        }

        $remainingPasskeys = $this->customerPasskeyRepository->countByShopUser($user) - 1;
        $socialLinks = $this->customerLinkRepository->countByShopUser($user);

        return ($remainingPasskeys + $socialLinks) >= 1;
    }

    public function canRemovePasskeyForAdminUser(AdminUserInterface $user): bool
    {
        if ($this->hasUsablePassword($user->getPassword(), SettingsScope::ADMIN)) {
            return true;
        }

        $remainingPasskeys = $this->adminPasskeyRepository->countByAdminUser($user) - 1;
        $socialLinks = $this->adminLinkRepository->countByAdminUser($user);

        return ($remainingPasskeys + $socialLinks) >= 1;
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
