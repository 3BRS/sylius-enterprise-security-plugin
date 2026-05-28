<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpBlacklist;

interface IpBlacklistCheckerInterface
{
    public function isFeatureEnabled(): bool;

    /**
     * Returns true if the IP is denied by the global CIDR list.
     */
    public function isBlockedByGlobal(string $ip): bool;
}
