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

    public function touch(string $sessionId): void;

    public function revoke(CustomerSessionInterface $session): void;

    public function revokeOthers(string $currentSessionId, ShopUserInterface $user): void;
}
