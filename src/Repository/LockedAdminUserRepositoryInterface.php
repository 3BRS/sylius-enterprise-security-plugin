<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableAdminUserInterface;

interface LockedAdminUserRepositoryInterface
{
    /** @return list<AdminUserInterface&LockableAdminUserInterface> */
    public function findAllLocked(): array;
}
