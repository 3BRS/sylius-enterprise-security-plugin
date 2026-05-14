<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpWhitelist;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpWhitelistRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

class IpWhitelistChecker implements IpWhitelistCheckerInterface
{
    public function __construct(
        protected SettingsProviderInterface $settingsProvider,
        protected AdminUserIpWhitelistRepositoryInterface $adminWhitelistRepository,
    ) {
    }

    public function isFeatureEnabled(): bool
    {
        return $this->settingsProvider->getBool('ip_whitelist.enabled', SettingsScope::GLOBAL);
    }

    public function isAllowedByGlobal(string $ip): bool
    {
        return $this->matchesAny($ip, $this->getGlobalCidrs());
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

        return $this->matchesAny($ip, $entity->getCidrs());
    }

    public function getGlobalCidrs(): array
    {
        $value = $this->settingsProvider->get('ip_whitelist.global_cidrs', SettingsScope::GLOBAL);
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

    /**
     * @param list<string> $cidrs
     */
    protected function matchesAny(string $ip, array $cidrs): bool
    {
        if ($ip === '' || $cidrs === []) {
            return false;
        }

        return IpUtils::checkIp($ip, $cidrs);
    }
}
