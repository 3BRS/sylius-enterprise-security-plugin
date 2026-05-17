<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockoutPolicyInterface;
use ThreeBRS\EnterpriseSecurityBundle\RateLimit\RateLimitGuardInterface;

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

        // Pessimistic row lock on the user — without it, concurrent failed-login requests
        // would race the read-modify-write of failedLoginAttempts and collapse multiple
        // increments into one, letting an attacker exceed maxAttempts before lockout
        // engages. Refresh re-reads the latest counter under the lock; commit releases
        // it. Scoped to this manager so unrelated user updates (e.g. trusted-device
        // revocation) are unaffected. Uses explicit begin/commit/rollback rather than
        // wrapInTransaction so it stays compatible with Doctrine ORM 2.x.
        $this->entityManager->beginTransaction();

        try {
            $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($user);

            $now = $this->clock->now();

            // If a previous lockout has already elapsed, start a fresh counter — otherwise
            // the next failure would immediately re-trip the lockout at maxAttempts.
            $lockoutUntil = $user->getLockoutUntil();
            if ($user->getLockedAt() !== null && $lockoutUntil !== null && $lockoutUntil <= $now) {
                $user->setFailedLoginAttempts(0);
                $user->setLockedAt(null);
                $user->setLockoutUntil(null);
            }

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
            $this->entityManager->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }
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

        // Pure query — never mutates. An expired auto-unlock window reads as "not locked";
        // the persistent cleanup happens in recordFailure / recordSuccess / unlock.
        $until = $user->getLockoutUntil();
        if ($until !== null && $until <= $this->clock->now()) {
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
