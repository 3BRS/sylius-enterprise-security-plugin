<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

interface ScopedFeatureCheckerInterface
{
    /**
     * Whether the feature is actually in effect for the group.
     */
    public function isEnabled(SettingsScope $scope): bool;
}
