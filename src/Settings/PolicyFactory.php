<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordPolicy;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordPolicyInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorMode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\LockoutPolicy;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\LockoutPolicyInterface;

class PolicyFactory implements PolicyFactoryInterface
{
    public function __construct(
        protected SettingsProviderInterface $provider,
    ) {
    }

    public function passwordPolicy(SettingsScope $scope): PasswordPolicyInterface
    {
        return new PasswordPolicy(
            $this->provider->getInt('password_policy.min_length', $scope),
            $this->provider->getNullableInt('password_policy.max_length', $scope),
            $this->provider->getBool('password_policy.require_uppercase', $scope),
            $this->provider->getBool('password_policy.require_lowercase', $scope),
            $this->provider->getBool('password_policy.require_numbers', $scope),
            $this->provider->getBool('password_policy.require_special_characters', $scope),
        );
    }

    public function lockoutPolicy(SettingsScope $scope): LockoutPolicyInterface
    {
        return new LockoutPolicy(
            $this->provider->getBool('account_lockout.enabled', $scope),
            $this->provider->getInt('account_lockout.max_attempts', $scope),
            $this->provider->getNullableInt('account_lockout.auto_unlock_after', $scope),
        );
    }

    public function twoFactorMode(SettingsScope $scope): TwoFactorMode
    {
        return TwoFactorMode::from($this->provider->getString('two_factor_authentication.mode', $scope));
    }
}
