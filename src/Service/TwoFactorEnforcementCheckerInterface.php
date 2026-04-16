<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserInterface;

interface TwoFactorEnforcementCheckerInterface
{
    public function shouldEnforceForShopUser(ShopUserInterface&TwoFactorAuthShopUserInterface $user): bool;

    public function shouldEnforceForAdminUser(AdminUserInterface&TwoFactorAuthAdminUserInterface $user): bool;
}
