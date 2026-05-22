<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpBlacklist;

use Sylius\Component\Core\Model\AdminUserInterface;

interface IpBlacklistCheckerInterface
{
    public function isFeatureEnabled(): bool;

    /**
     * Returns true if the IP is denied by the global CIDR list, regardless of any
     * per-admin blacklist.
     */
    public function isBlockedByGlobal(string $ip): bool;

    /**
     * Pre-authentication check used on the admin login page. True if the IP matches
     * the global list OR ANY enabled per-admin entry covers the IP — the second
     * arm denies the IP at the login form already, even before we know the admin
     * identity. Identity-bound enforcement still re-runs post-authentication via
     * isBlockedForAdmin().
     */
    public function isBlockedAnonymously(string $ip): bool;

    /**
     * Post-authentication check. True if the IP matches the global CIDR list
     * OR the authenticated admin's own (enabled) per-admin CIDR list.
     */
    public function isBlockedForAdmin(AdminUserInterface $admin, string $ip): bool;

    /**
     * @return list<string>
     */
    public function getGlobalCidrs(): array;
}
