<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasswordHistoryInterface;

interface AdminUserPasswordHistoryRepositoryInterface
{
    /**
     * @return AdminUserPasswordHistoryInterface[]
     */
    public function findRecentByAdminUser(AdminUserInterface $user, int $count): array;

    public function deleteOldEntriesForAdminUser(AdminUserInterface $user, int $keepCount): void;
}
