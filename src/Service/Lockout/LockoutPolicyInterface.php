<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout;

interface LockoutPolicyInterface
{
    public function isEnabled(): bool;

    public function getMaxAttempts(): int;

    public function getLockoutDuration(): int;

    public function getAutoUnlockAfter(): ?int;
}
