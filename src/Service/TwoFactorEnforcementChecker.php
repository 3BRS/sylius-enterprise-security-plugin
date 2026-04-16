<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorMode;

class TwoFactorEnforcementChecker implements TwoFactorEnforcementCheckerInterface
{
    public function __construct(
        private TwoFactorMode $customerMode,
        private TwoFactorMode $adminMode,
    ) {
    }

    public function shouldEnforceForShopUser(ShopUserInterface&TwoFactorAuthShopUserInterface $user): bool
    {
        return $this->customerMode->isEnforced() && !$user->isTwoFactorEnabled();
    }

    public function shouldEnforceForAdminUser(AdminUserInterface&TwoFactorAuthAdminUserInterface $user): bool
    {
        return $this->adminMode->isEnforced() && !$user->isTwoFactorEnabled();
    }
}
