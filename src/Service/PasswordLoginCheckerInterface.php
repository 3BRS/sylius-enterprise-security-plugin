<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

interface PasswordLoginCheckerInterface
{
    /**
     * Whether email + password login is enabled (the global per-scope toggle). When false,
     * password login is off for the whole group — login form, registration and the
     * change/set-password actions are all suppressed for that scope.
     */
    public function isEnabled(SettingsScope $scope): bool;
}
