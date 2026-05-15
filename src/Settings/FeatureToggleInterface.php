<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

interface FeatureToggleInterface
{
    public function isEnabled(string $feature, SettingsScope $scope): bool;

    public function isTwoFactorActive(SettingsScope $scope): bool;
}
