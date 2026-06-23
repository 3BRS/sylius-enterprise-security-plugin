<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
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

    protected function hasUsablePassword(?string $password): bool
    {
        return $password !== null && $password !== '';
    }
}
