<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;

interface CustomerSessionTrackerInterface
{
    public function track(
        ShopUserInterface $user,
        string $sessionId,
        ?string $userAgent,
        ?string $ipAddress,
    ): CustomerSessionInterface;

    /**
     * Moves the session recorded under the previous ID to the new one, when it belongs to the user, has
     * not been revoked and nothing is recorded under the new ID yet.
     */
    public function moveSession(string $previousSessionId, string $sessionId, ShopUserInterface $user): void;

    public function touch(string $sessionId): void;

    public function revoke(CustomerSessionInterface $session): void;

    public function revokeOthers(string $currentSessionId, ShopUserInterface $user): void;

    /**
     * Revoke every session of the user that has not been revoked, expired ones included — used
     * by the admin "remote logout" action where no current session is being preserved.
     */
    public function revokeAll(ShopUserInterface $user): void;
}
