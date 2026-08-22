<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

/**
 * A feature answers to two switches: the configuration file, which says whether
 * the installation set it up at all, and Security Settings, which is where an
 * administrator turns it on and off day to day. Both have to agree — the
 * configuration cannot be overruled from the database, because it is what
 * supplies the plumbing a feature needs, and the setting has to be able to stop
 * a feature that is set up.
 *
 * One instance per feature, given the two configuration flags for that feature;
 * see the definitions in services.yaml.
 */
class ScopedFeatureChecker implements ScopedFeatureCheckerInterface
{
    public function __construct(
        protected FeatureToggleInterface $featureToggle,
        protected string $feature,
        protected bool $customerConfigured,
        protected bool $adminConfigured,
    ) {
    }

    public function isEnabled(SettingsScope $scope): bool
    {
        return $this->isConfigured($scope) && $this->featureToggle->isEnabled($this->feature, $scope);
    }

    protected function isConfigured(SettingsScope $scope): bool
    {
        return match ($scope) {
            SettingsScope::ADMIN => $this->adminConfigured,
            default => $this->customerConfigured,
        };
    }
}
