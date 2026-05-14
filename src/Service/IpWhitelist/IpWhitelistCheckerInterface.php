<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpWhitelist;

use Sylius\Component\Core\Model\AdminUserInterface;

interface IpWhitelistCheckerInterface
{
    public function isFeatureEnabled(): bool;

    /**
     * Returns true if the IP is allowed by the global CIDR list, regardless of any
     * per-admin whitelist (used during the pre-authentication check on the login page).
     */
    public function isAllowedByGlobal(string $ip): bool;

    /**
     * Returns true if the admin is allowed in for this IP: matches the global CIDR list
     * OR matches the admin's own (enabled) per-admin CIDR list.
     */
    public function isAllowedForAdmin(AdminUserInterface $admin, string $ip): bool;

    /**
     * @return list<string>
     */
    public function getGlobalCidrs(): array;
}
