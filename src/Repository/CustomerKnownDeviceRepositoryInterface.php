<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerKnownDeviceRepositoryInterface
{
    public function existsForShopUser(ShopUserInterface $user, string $fingerprint): bool;
}
