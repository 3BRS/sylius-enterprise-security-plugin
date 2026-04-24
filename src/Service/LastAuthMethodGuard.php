<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;

class LastAuthMethodGuard implements LastAuthMethodGuardInterface
{
    public function __construct(
        protected CustomerSocialAccountLinkRepositoryInterface $customerLinkRepository,
        protected AdminUserSocialAccountLinkRepositoryInterface $adminLinkRepository,
    ) {
    }

    public function canUnlinkSocialForShopUser(ShopUserInterface $user, string $provider): bool
    {
        if ($this->hasUsablePassword($user->getPassword())) {
            return true;
        }

        $total = $this->customerLinkRepository->countByShopUser($user);
        $link = $this->customerLinkRepository->findOneByShopUserAndProvider($user, $provider);

        if ($link === null) {
            return true;
        }

        return $total > 1;
    }

    public function canUnlinkSocialForAdminUser(AdminUserInterface $user, string $provider): bool
    {
        if ($this->hasUsablePassword($user->getPassword())) {
            return true;
        }

        $total = $this->adminLinkRepository->countByAdminUser($user);
        $link = $this->adminLinkRepository->findOneByAdminUserAndProvider($user, $provider);

        if ($link === null) {
            return true;
        }

        return $total > 1;
    }

    protected function hasUsablePassword(?string $password): bool
    {
        return $password !== null && $password !== '';
    }
}
