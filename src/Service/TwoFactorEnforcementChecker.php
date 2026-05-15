<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\PolicyFactoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserInterface;

class TwoFactorEnforcementChecker implements TwoFactorEnforcementCheckerInterface
{
    public function __construct(
        protected PolicyFactoryInterface $policyFactory,
    ) {
    }

    public function shouldEnforceForShopUser(ShopUserInterface&TwoFactorAuthShopUserInterface $user): bool
    {
        return $this->policyFactory->twoFactorMode(SettingsScope::CUSTOMER)->isEnforced() && !$user->isTwoFactorEnabled();
    }

    public function shouldEnforceForAdminUser(AdminUserInterface&TwoFactorAuthAdminUserInterface $user): bool
    {
        return $this->policyFactory->twoFactorMode(SettingsScope::ADMIN)->isEnforced() && !$user->isTwoFactorEnabled();
    }
}
