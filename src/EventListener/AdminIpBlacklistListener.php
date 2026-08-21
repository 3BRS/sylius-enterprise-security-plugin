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
 * Listener priority 20 in services.yaml runs this above the firewall (8) and
 * ahead of both of the whitelist listener's passes (18 and 4), so a blacklist
 * hit short-circuits via setResponse() and no allow decision can override it.
 * Above the firewall matters because the authenticator answers
 * /admin/login-check and /admin/2fa_check itself: below it, a blocked address
 * could still post to the login form and drive an administrator's lockout
 * counter.
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
