<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait LockableShopUserTrait
{
    #[ORM\Column(name: 'failed_login_attempts', type: 'integer', options: ['default' => 0])]
    protected int $failedLoginAttempts = 0;

    #[ORM\Column(name: 'last_failed_login_at', type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $lastFailedLoginAt = null;

    #[ORM\Column(name: 'locked_at', type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $lockedAt = null;

    #[ORM\Column(name: 'lockout_until', type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $lockoutUntil = null;

    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function setFailedLoginAttempts(int $failedLoginAttempts): void
    {
        $this->failedLoginAttempts = $failedLoginAttempts;
    }

    public function getLastFailedLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastFailedLoginAt;
    }

    public function setLastFailedLoginAt(?\DateTimeImmutable $lastFailedLoginAt): void
    {
        $this->lastFailedLoginAt = $lastFailedLoginAt;
    }

    public function getLockedAt(): ?\DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function setLockedAt(?\DateTimeImmutable $lockedAt): void
    {
        $this->lockedAt = $lockedAt;
    }

    public function getLockoutUntil(): ?\DateTimeImmutable
    {
        return $this->lockoutUntil;
    }

    public function setLockoutUntil(?\DateTimeImmutable $lockoutUntil): void
    {
        $this->lockoutUntil = $lockoutUntil;
    }
}
