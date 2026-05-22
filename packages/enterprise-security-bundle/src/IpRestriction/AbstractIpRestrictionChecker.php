<?php

declare(strict_types=1);

namespace ThreeBRS\EnterpriseSecurityBundle\IpRestriction;

use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpWhitelist\CidrMatcherInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

/**
 * Generic orchestration for an IP restriction feature (whitelist OR blacklist).
 * Combines the global CIDR list from settings, an optional per-user CIDR list
 * from the repository, and the bundle's `CidrMatcher`. Subclass binds the
 * settings key + scope; matching semantics (allow vs deny) are decided by the
 * caller (a "match" means "this CIDR list covers the IP", interpretation is
 * scope-specific).
 */
abstract class AbstractIpRestrictionChecker
{
    public function __construct(
        protected SettingsProviderInterface $settingsProvider,
        protected IpRestrictionRepositoryInterface $repository,
        protected CidrMatcherInterface $cidrMatcher,
    ) {
    }

    public function isFeatureEnabled(): bool
    {
        return $this->settingsProvider->getBool($this->getSettingsKey() . '.enabled', $this->getScope());
    }

    public function matchesGlobal(string $ip): bool
    {
        return $this->cidrMatcher->matchesAny($ip, $this->getGlobalCidrs());
    }

    /**
     * Anonymous check (identity not yet known). Returns true if the IP matches
     * the global list OR ANY enabled per-user entry. For whitelist semantics
     * this is "let the request through to the login form"; for blacklist
     * semantics this is "the IP is denied even before we identify the user".
     */
    public function matchesAnonymously(string $ip): bool
    {
        if ($this->matchesGlobal($ip)) {
            return true;
        }

        if ($ip === '') {
            return false;
        }

        foreach ($this->repository->findAllEnabled() as $entry) {
            if ($this->cidrMatcher->matchesAny($ip, $entry->getCidrs())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Authenticated check. Returns true if the IP matches the global list
     * OR the supplied user's own (enabled) per-user CIDR list.
     */
    public function matchesForUser(UserInterface $user, string $ip): bool
    {
        if ($this->matchesGlobal($ip)) {
            return true;
        }

        $entity = $this->repository->findOneByUser($user);
        if ($entity === null || ! $entity->isEnabled()) {
            return false;
        }

        return $this->cidrMatcher->matchesAny($ip, $entity->getCidrs());
    }

    /**
     * @return list<string>
     */
    public function getGlobalCidrs(): array
    {
        $value = $this->settingsProvider->get($this->getSettingsKey() . '.global_cidrs', $this->getScope());
        if (! is_array($value)) {
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
     * Settings key prefix (e.g. `ip_whitelist`, `ip_blacklist`) for the
     * `.enabled` and `.global_cidrs` lookups.
     */
    abstract protected function getSettingsKey(): string;

    abstract protected function getScope(): SettingsScope;
}
