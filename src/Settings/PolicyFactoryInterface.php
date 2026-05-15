<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordPolicyInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorMode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\LockoutPolicyInterface;

interface PolicyFactoryInterface
{
    public function passwordPolicy(SettingsScope $scope): PasswordPolicyInterface;

    public function lockoutPolicy(SettingsScope $scope): LockoutPolicyInterface;

    public function twoFactorMode(SettingsScope $scope): TwoFactorMode;
}
