<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpBlacklist;

use ThreeBRS\EnterpriseSecurityBundle\IpRestriction\AbstractIpRestrictionChecker;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

/**
 * Plugin-side blacklist semantics on top of the bundle's generic
 * AbstractIpRestrictionChecker. The bundle gives us "does the global CIDR list
 * cover this IP?"; we re-label that as "is the IP blocked?" — which for the
 * blacklist scope means the same thing (a list match denies access).
 */
class IpBlacklistChecker extends AbstractIpRestrictionChecker implements IpBlacklistCheckerInterface
{
    public function isBlockedByGlobal(string $ip): bool
    {
        return $this->matchesGlobal($ip);
    }

    protected function getSettingsKey(): string
    {
        return 'ip_blacklist';
    }

    protected function getScope(): SettingsScope
    {
        return SettingsScope::ADMIN;
    }
}
