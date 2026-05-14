<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpWhitelist;

use Sylius\Component\Core\Model\AdminUserInterface;

interface IpWhitelistCheckerInterface
{
    public function isFeatureEnabled(): bool;

    /**
     * Returns true if the IP is allowed by the global CIDR list, regardless of any
     * per-admin whitelist.
     */
    public function isAllowedByGlobal(string $ip): bool;

    /**
     * Pre-authentication check used on the admin login page. True if the IP matches
     * the global list OR ANY enabled per-admin entry covers the IP — the latter is
     * what lets a "pure per-admin" setup (no global CIDRs) reach the login form at
     * all. Identity-bound enforcement still happens post-authentication via
     * isAllowedForAdmin().
     */
    public function isAllowedAnonymously(string $ip): bool;

    /**
     * Post-authentication check. True if the IP matches the global CIDR list
     * OR the authenticated admin's own (enabled) per-admin CIDR list.
     */
    public function isAllowedForAdmin(AdminUserInterface $admin, string $ip): bool;

    /**
     * @return list<string>
     */
    public function getGlobalCidrs(): array;
}
