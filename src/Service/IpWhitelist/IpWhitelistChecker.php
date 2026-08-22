<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpWhitelist;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpRestriction\AbstractIpRestrictionChecker;
use ThreeBRS\EnterpriseSecurityBundle\IpWhitelist\CidrMatcherInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpWhitelistRepositoryInterface;

/**
 * Plugin-side whitelist semantics on top of the bundle's generic
 * AbstractIpRestrictionChecker, the same base the blacklist checker uses. The
 * bundle answers "does the global CIDR list cover this IP?"; for the whitelist
 * scope that means the address is allowed, and on top of it the plugin adds the
 * per-admin lists, which have no counterpart on the blacklist side.
 */
class IpWhitelistChecker extends AbstractIpRestrictionChecker implements IpWhitelistCheckerInterface
{
    public function __construct(
        SettingsProviderInterface $settingsProvider,
        protected AdminUserIpWhitelistRepositoryInterface $adminWhitelistRepository,
        CidrMatcherInterface $cidrMatcher,
    ) {
        parent::__construct($settingsProvider, $cidrMatcher);
    }

    public function isAllowedByGlobal(string $ip): bool
    {
        return $this->matchesGlobal($ip);
    }

    public function isAllowedAnonymously(string $ip): bool
    {
        if ($this->isAllowedByGlobal($ip)) {
            return true;
        }

        if ($ip === '') {
            return false;
        }

        foreach ($this->adminWhitelistRepository->findAllEnabled() as $entry) {
            if ($this->cidrMatcher->matchesAny($ip, $entry->getCidrs())) {
                return true;
            }
        }

        return false;
    }

    public function isAllowedForAdmin(AdminUserInterface $admin, string $ip): bool
    {
        if ($this->isAllowedByGlobal($ip)) {
            return true;
        }

        $entity = $this->adminWhitelistRepository->findOneByAdminUser($admin);
        if ($entity === null || !$entity->isEnabled()) {
            return false;
        }

        return $this->cidrMatcher->matchesAny($ip, $entity->getCidrs());
    }

    protected function getSettingsKey(): string
    {
        return 'ip_whitelist';
    }

    protected function getScope(): SettingsScope
    {
        return SettingsScope::ADMIN;
    }
}
