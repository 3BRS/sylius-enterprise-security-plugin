<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use ThreeBRS\EnterpriseSecurityBundle\IpRestriction\AbstractIpRestrictionListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpBlacklist\IpBlacklistCheckerInterface;

/**
 * Blacklist-flavoured request listener. The bundle's
 * AbstractIpRestrictionListener owns the framework plumbing (path match,
 * client IP, 403 response); this subclass plugs in the blacklist feature
 * toggle and inverts the "matches" check into a "deny" decision — a global
 * CIDR hit means the IP is denied.
 *
 * Listener priority 5 in services.yaml runs this BEFORE the whitelist
 * listener (priority 4), so a blacklist hit short-circuits via setResponse()
 * and the whitelist's allow-gate never gets a chance to override.
 */
class AdminIpBlacklistListener extends AbstractIpRestrictionListener implements AdminIpBlacklistListenerInterface
{
    public function __construct(
        protected IpBlacklistCheckerInterface $checker,
        string $adminPathPrefix = '/admin',
    ) {
        parent::__construct($adminPathPrefix);
    }

    protected function isFeatureEnabled(): bool
    {
        return $this->checker->isFeatureEnabled();
    }

    protected function isRequestAllowed(string $ip): bool
    {
        return !$this->checker->isBlockedByGlobal($ip);
    }
}
