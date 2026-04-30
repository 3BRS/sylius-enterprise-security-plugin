<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RateLimit\RateLimitGuardInterface;

class ShopUserLockoutManager implements ShopUserLockoutManagerInterface
{
    public function __construct(
        protected LockoutPolicyInterface $policy,
        protected EntityManagerInterface $entityManager,
        protected ClockInterface $clock,
        protected RateLimitGuardInterface $rateLimitGuard,
    ) {
    }

    public function recordFailure(LockableShopUserInterface $user): void
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

    public function recordSuccess(LockableShopUserInterface $user): void
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

    public function isLocked(LockableShopUserInterface $user): bool
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

    public function unlock(LockableShopUserInterface $user): void
    {
        $user->setFailedLoginAttempts(0);
        $user->setLastFailedLoginAt(null);
        $user->setLockedAt(null);
        $user->setLockoutUntil(null);

        $this->entityManager->flush();

        // Without clearing the rate-limit counter, an unlocked user would still be
        // blocked at HTTP layer until the rate-limit window expires — making admin
        // unlock effectively useless. Sylius shop login is email-only (the
        // username field on ShopUser is auto-mirrored from the customer email by
        // DefaultUsernameORMListener), so resetting the email key is sufficient.
        if ($user instanceof ShopUserInterface) {
            $email = $user->getEmail();
            if ($email !== null && $email !== '') {
                $this->rateLimitGuard->reset('customer', 'login', $email);
            }
        }
    }
}
