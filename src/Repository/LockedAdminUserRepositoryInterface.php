<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockedUserRepositoryInterface;

interface LockedAdminUserRepositoryInterface extends LockedUserRepositoryInterface
{
    /** @return list<AdminUserInterface&LockableAdminUserInterface> */
    public function findAllLocked(): array;
}
