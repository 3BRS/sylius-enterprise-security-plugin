<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Model;

interface LockableAdminUserInterface
{
    public function getFailedLoginAttempts(): int;

    public function setFailedLoginAttempts(int $failedLoginAttempts): void;

    public function getLastFailedLoginAt(): ?\DateTimeImmutable;

    public function setLastFailedLoginAt(?\DateTimeImmutable $lastFailedLoginAt): void;

    public function getLockedAt(): ?\DateTimeImmutable;

    public function setLockedAt(?\DateTimeImmutable $lockedAt): void;

    public function getLockoutUntil(): ?\DateTimeImmutable;

    public function setLockoutUntil(?\DateTimeImmutable $lockoutUntil): void;
}
