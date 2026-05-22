<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockedUserRepositoryInterface;

interface LockedShopUserRepositoryInterface extends LockedUserRepositoryInterface
{
    /** @return list<ShopUserInterface&LockableShopUserInterface> */
    public function findAllLocked(): array;
}
