<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;

interface LockedShopUserRepositoryInterface
{
    /** @return list<ShopUserInterface&LockableShopUserInterface> */
    public function findAllLocked(): array;
}
