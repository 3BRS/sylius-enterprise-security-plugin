<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;

interface RecoveryCodeVerifierInterface
{
    public function verifyAndConsumeForShopUser(ShopUserInterface $user, string $plainCode): bool;

    public function verifyAndConsumeForAdminUser(AdminUserInterface $user, string $plainCode): bool;
}
