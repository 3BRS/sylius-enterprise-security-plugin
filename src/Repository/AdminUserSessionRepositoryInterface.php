<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSessionInterface;

interface AdminUserSessionRepositoryInterface
{
    public function findOneBySessionId(string $sessionId): ?AdminUserSessionInterface;

    /**
     * Sessions that are neither revoked nor expired, most recent activity first.
     *
     * @return list<AdminUserSessionInterface>
     */
    public function findActiveForAdminUser(AdminUserInterface $user): array;

    /**
     * Every session that has not been revoked, expired ones included. A session can still be
     * signed in after it expired: when the configured lifetime is shorter than the session handler
     * keeps sessions, or before PHP's session garbage collection removes it.
     *
     * @return list<AdminUserSessionInterface>
     */
    public function findUnrevokedForAdminUser(AdminUserInterface $user): array;

    /**
     * A session of the user that has not been revoked, expired or not.
     */
    public function findActiveByIdForAdminUser(int $id, AdminUserInterface $user): ?AdminUserSessionInterface;
}
