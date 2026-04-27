<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserInterface;

class AdminUserLockoutManager implements AdminUserLockoutManagerInterface
{
    public function __construct(
        protected LockoutPolicyInterface $policy,
        protected EntityManagerInterface $entityManager,
        protected ClockInterface $clock,
    ) {
    }

    public function recordFailure(LockableAdminUserInterface $user): void
    {
        if (!$this->policy->isEnabled()) {
            return;
        }

        $now = $this->clock->now();
        $attempts = $user->getFailedLoginAttempts() + 1;

        $user->setFailedLoginAttempts($attempts);
        $user->setLastFailedLoginAt($now);

        if ($attempts >= $this->policy->getMaxAttempts()) {
            $user->setLockedAt($now);
            $autoUnlockAfter = $this->policy->getAutoUnlockAfter();
            $user->setLockoutUntil(
                $autoUnlockAfter === null ? null : $now->modify(sprintf('+%d seconds', $autoUnlockAfter)),
            );
        }

        $this->entityManager->flush();
    }

    public function recordSuccess(LockableAdminUserInterface $user): void
    {
        if ($user->getFailedLoginAttempts() === 0 && $user->getLockedAt() === null) {
            return;
        }

        $user->setFailedLoginAttempts(0);
        $user->setLastFailedLoginAt(null);
        $user->setLockedAt(null);
        $user->setLockoutUntil(null);

        $this->entityManager->flush();
    }

    public function isLocked(LockableAdminUserInterface $user): bool
    {
        if (!$this->policy->isEnabled()) {
            return false;
        }

        if ($user->getLockedAt() === null) {
            return false;
        }

        $until = $user->getLockoutUntil();
        if ($until !== null && $until <= $this->clock->now()) {
            $this->unlock($user);

            return false;
        }

        return true;
    }

    public function unlock(LockableAdminUserInterface $user): void
    {
        $user->setFailedLoginAttempts(0);
        $user->setLastFailedLoginAt(null);
        $user->setLockedAt(null);
        $user->setLockoutUntil(null);

        $this->entityManager->flush();
    }
}
