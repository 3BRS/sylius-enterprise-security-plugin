<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

class FeatureToggle implements FeatureToggleInterface
{
    public function __construct(
        protected SettingsProviderInterface $provider,
    ) {
    }

    public function isEnabled(string $feature, SettingsScope $scope): bool
    {
        return $this->provider->getBool($feature . '.enabled', $scope);
    }
}
