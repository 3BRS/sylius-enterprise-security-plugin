<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpWhitelist;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpWhitelist\CidrMatcherInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpWhitelistRepositoryInterface;

class IpWhitelistChecker implements IpWhitelistCheckerInterface
{
    public function __construct(
        protected SettingsProviderInterface $settingsProvider,
        protected AdminUserIpWhitelistRepositoryInterface $adminWhitelistRepository,
        protected CidrMatcherInterface $cidrMatcher,
    ) {
    }

    public function isFeatureEnabled(): bool
    {
        return $this->settingsProvider->getBool('ip_whitelist.enabled', SettingsScope::ADMIN);
    }

    public function isAllowedByGlobal(string $ip): bool
    {
        return $this->cidrMatcher->matchesAny($ip, $this->getGlobalCidrs());
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

    public function getGlobalCidrs(): array
    {
        $value = $this->settingsProvider->get('ip_whitelist.global_cidrs', SettingsScope::ADMIN);
        if (!is_array($value)) {
            return [];
        }

        $cidrs = [];
        foreach ($value as $cidr) {
            if (is_string($cidr) && $cidr !== '') {
                $cidrs[] = $cidr;
            }
        }

        return $cidrs;
    }
}
