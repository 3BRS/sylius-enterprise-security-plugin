<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RateLimit\RateLimitGuardInterface;

class AdminUserLockoutManager implements AdminUserLockoutManagerInterface
{
    public function __construct(
        protected LockoutPolicyInterface $policy,
        protected EntityManagerInterface $entityManager,
        protected ClockInterface $clock,
        protected RateLimitGuardInterface $rateLimitGuard,
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

        // Without clearing the rate-limit counter, an unlocked admin would still be
        // blocked at HTTP layer until the rate-limit window expires — making admin
        // unlock effectively useless. Reset for both username and email since either
        // could have been used as the form's _username field.
        if ($user instanceof AdminUserInterface) {
            $username = $user->getUsername();
            if ($username !== null && $username !== '') {
                $this->rateLimitGuard->reset('admin', 'login', $username);
            }

            $email = $user->getEmail();
            if ($email !== null && $email !== '' && $email !== $username) {
                $this->rateLimitGuard->reset('admin', 'login', $email);
            }
        }
    }
}
