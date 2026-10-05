<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSessionInterface;

interface AdminUserSessionTrackerInterface
{
    public function track(
        AdminUserInterface $user,
        string $sessionId,
        ?string $userAgent,
        ?string $ipAddress,
    ): AdminUserSessionInterface;

    /**
     * Moves the session recorded under the previous ID to the new one, when it belongs to the user, has
     * not been revoked and nothing is recorded under the new ID yet.
     */
    public function moveSession(string $previousSessionId, string $sessionId, AdminUserInterface $user): void;

    public function touch(string $sessionId): void;

    public function revoke(AdminUserSessionInterface $session): void;

    public function revokeOthers(string $currentSessionId, AdminUserInterface $user): void;
}
