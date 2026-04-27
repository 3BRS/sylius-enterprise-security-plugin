<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserInterface;

interface AdminUserLockoutManagerInterface
{
    public function recordFailure(LockableAdminUserInterface $user): void;

    public function recordSuccess(LockableAdminUserInterface $user): void;

    public function isLocked(LockableAdminUserInterface $user): bool;

    public function unlock(LockableAdminUserInterface $user): void;
}
