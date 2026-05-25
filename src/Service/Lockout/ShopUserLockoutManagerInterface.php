<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout;

use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableShopUserInterface;

interface ShopUserLockoutManagerInterface
{
    public function recordFailure(LockableShopUserInterface $user): void;

    public function recordSuccess(LockableShopUserInterface $user): void;

    public function isLocked(LockableShopUserInterface $user): bool;

    public function unlock(LockableShopUserInterface $user): void;
}
