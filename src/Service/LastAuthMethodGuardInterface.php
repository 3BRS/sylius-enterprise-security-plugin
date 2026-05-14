<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;

interface LastAuthMethodGuardInterface
{
    public function canUnlinkSocialForShopUser(ShopUserInterface $user, string $provider): bool;

    public function canUnlinkSocialForAdminUser(AdminUserInterface $user, string $provider): bool;
}
