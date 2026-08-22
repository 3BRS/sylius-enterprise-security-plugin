<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

/**
 * Passkeys answer to two switches: the one in the configuration file, which the
 * login and registration controllers read, and the one in Security Settings,
 * which the account menu reads. They agree unless an administrator ticks the
 * settings box on an installation whose configuration leaves passkeys off, and
 * then a stored credential is listed but opens nothing.
 *
 * Callers deciding whether a passkey is a way back into an account need the
 * conservative answer, so both have to say yes.
 */
class PasskeyChecker implements PasskeyCheckerInterface
{
    public function __construct(
        protected FeatureToggleInterface $featureToggle,
        protected bool $customerConfigured,
        protected bool $adminConfigured,
    ) {
    }

    public function isEnabled(SettingsScope $scope): bool
    {
        return $this->isConfigured($scope) && $this->featureToggle->isEnabled('passkey', $scope);
    }

    protected function isConfigured(SettingsScope $scope): bool
    {
        return match ($scope) {
            SettingsScope::ADMIN => $this->adminConfigured,
            default => $this->customerConfigured,
        };
    }
}
