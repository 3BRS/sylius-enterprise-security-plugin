<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSessionInterface;

interface AdminUserSessionRepositoryInterface
{
    public function findOneBySessionId(string $sessionId): ?AdminUserSessionInterface;

    /** @return list<AdminUserSessionInterface> */
    public function findActiveForAdminUser(AdminUserInterface $user): array;

    public function findActiveByIdForAdminUser(int $id, AdminUserInterface $user): ?AdminUserSessionInterface;
}
