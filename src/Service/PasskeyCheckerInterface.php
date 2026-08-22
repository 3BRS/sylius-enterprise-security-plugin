<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

interface PasskeyCheckerInterface
{
    /**
     * Whether a registered passkey actually opens a session for the scope.
     */
    public function isEnabled(SettingsScope $scope): bool;
}
