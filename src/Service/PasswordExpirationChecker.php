<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationShopUserInterface;

class PasswordExpirationChecker implements PasswordExpirationCheckerInterface
{
    public function __construct(
        private bool $customerEnabled,
        private int $customerDays,
        private bool $adminEnabled,
        private int $adminDays,
    ) {
    }

    public function isShopUserPasswordExpired(PasswordExpirationShopUserInterface $user): bool
    {
        if ($user->isForcePasswordChange()) {
            return true;
        }

        if (!$this->customerEnabled) {
            return false;
        }

        $changedAt = $user->getPasswordChangedAt();
        if ($changedAt === null) {
            return true;
        }

        $expiresAt = $changedAt->modify(sprintf('+%d days', $this->customerDays));

        return new \DateTimeImmutable() > $expiresAt;
    }

    public function isAdminUserPasswordExpired(PasswordExpirationAdminUserInterface $user): bool
    {
        if ($user->isForcePasswordChange()) {
            return true;
        }

        if (!$this->adminEnabled) {
            return false;
        }

        $changedAt = $user->getPasswordChangedAt();
        if ($changedAt === null) {
            return true;
        }

        $expiresAt = $changedAt->modify(sprintf('+%d days', $this->adminDays));

        return new \DateTimeImmutable() > $expiresAt;
    }
}
