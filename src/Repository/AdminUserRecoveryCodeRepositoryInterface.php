<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCodeInterface;

interface AdminUserRecoveryCodeRepositoryInterface
{
    public function findUnusedByAdminUserAndHash(AdminUserInterface $user, string $codeHash): ?AdminUserRecoveryCodeInterface;

    /**
     * @return array<int, AdminUserRecoveryCodeInterface>
     */
    public function findAllByAdminUser(AdminUserInterface $user): array;

    public function deleteAllByAdminUser(AdminUserInterface $user): int;
}
