<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

class PasswordExpirationChecker implements PasswordExpirationCheckerInterface
{
    public function __construct(
        protected SettingsProviderInterface $settings,
    ) {
    }

    public function isShopUserPasswordExpired(PasswordExpirationShopUserInterface $user): bool
    {
        if ($user->isForcePasswordChange()) {
            return true;
        }

        if (!$this->settings->getBool('password_expiration.enabled', SettingsScope::CUSTOMER)) {
            return false;
        }

        $changedAt = $user->getPasswordChangedAt();
        if ($changedAt === null) {
            return true;
        }

        $days = $this->settings->getInt('password_expiration.days', SettingsScope::CUSTOMER);
        $expiresAt = $changedAt->modify(sprintf('+%d days', $days));

        return new \DateTimeImmutable() > $expiresAt;
    }

    public function isAdminUserPasswordExpired(PasswordExpirationAdminUserInterface $user): bool
    {
        if ($user->isForcePasswordChange()) {
            return true;
        }

        if (!$this->settings->getBool('password_expiration.enabled', SettingsScope::ADMIN)) {
            return false;
        }

        $changedAt = $user->getPasswordChangedAt();
        if ($changedAt === null) {
            return true;
        }

        $days = $this->settings->getInt('password_expiration.days', SettingsScope::ADMIN);
        $expiresAt = $changedAt->modify(sprintf('+%d days', $days));

        return new \DateTimeImmutable() > $expiresAt;
    }
}
