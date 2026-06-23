<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

class PasswordLoginChecker implements PasswordLoginCheckerInterface
{
    public function __construct(
        protected FeatureToggleInterface $featureToggle,
    ) {
    }

    public function isEnabled(SettingsScope $scope): bool
    {
        return $this->featureToggle->isEnabled('password_login', $scope);
    }
}
