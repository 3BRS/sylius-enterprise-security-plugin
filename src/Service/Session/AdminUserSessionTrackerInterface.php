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

    public function touch(string $sessionId): void;

    public function revoke(AdminUserSessionInterface $session): void;

    public function revokeOthers(string $currentSessionId, AdminUserInterface $user): void;
}
