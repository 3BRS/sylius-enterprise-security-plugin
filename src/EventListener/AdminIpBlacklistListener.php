<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpRestriction\AbstractIpRestrictionListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpBlacklist\IpBlacklistCheckerInterface;

/**
 * Blacklist-flavoured request listener. The bundle's
 * AbstractIpRestrictionListener owns the framework plumbing (path match,
 * token resolve, 403 response); this subclass plugs in the blacklist
 * feature toggle and inverts the "matches" check into a "deny" decision —
 * a CIDR hit means the IP is denied.
 *
 * Listener priority 5 in services.yaml runs this BEFORE the whitelist
 * listener (priority 4), so a blacklist hit short-circuits via setResponse()
 * and the whitelist's allow-gate never gets a chance to override.
 */
class AdminIpBlacklistListener extends AbstractIpRestrictionListener implements AdminIpBlacklistListenerInterface
{
    public function __construct(
        protected IpBlacklistCheckerInterface $checker,
        TokenStorageInterface $tokenStorage,
        string $adminPathPrefix = '/admin',
    ) {
        parent::__construct($tokenStorage, $adminPathPrefix);
    }

    protected function isFeatureEnabled(): bool
    {
        return $this->checker->isFeatureEnabled();
    }

    protected function isRequestAllowed(?UserInterface $user, string $ip): bool
    {
        if ($user instanceof AdminUserInterface) {
            return !$this->checker->isBlockedForAdmin($user, $ip);
        }

        return !$this->checker->isBlockedAnonymously($ip);
    }
}
