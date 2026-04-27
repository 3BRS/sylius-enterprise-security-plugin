<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Lockout;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\AdminUserLockoutManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\LockoutPolicy;

#[CoversClass(AdminUserLockoutManager::class)]
class AdminUserLockoutManagerTest extends TestCase
{
    public function testRecordFailureLocksAccountAtThreshold(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, lockoutDuration: 1800, autoUnlockAfter: 1800);
        $em = $this->createStub(EntityManagerInterface::class);

        $now = new \DateTimeImmutable('2026-04-27 10:00:00');
        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'));
        $user = $this->createUser();
        $user->setFailedLoginAttempts(2);

        $manager->recordFailure($user);

        self::assertSame(3, $user->getFailedLoginAttempts());
        self::assertEquals($now, $user->getLockedAt());
        self::assertEquals($now->modify('+1800 seconds'), $user->getLockoutUntil());
    }

    public function testIsLockedAutoUnlocksWhenLockoutUntilIsInThePast(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, lockoutDuration: 60, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:30:00'));
        $user = $this->createUser();
        $user->setLockedAt(new \DateTimeImmutable('2026-04-27 10:00:00'));
        $user->setLockoutUntil(new \DateTimeImmutable('2026-04-27 10:15:00'));
        $user->setFailedLoginAttempts(3);

        self::assertFalse($manager->isLocked($user));
        self::assertNull($user->getLockedAt());
    }

    public function testRecordSuccessResetsLockoutState(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, lockoutDuration: 60, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'));
        $user = $this->createUser();
        $user->setFailedLoginAttempts(2);

        $manager->recordSuccess($user);

        self::assertSame(0, $user->getFailedLoginAttempts());
    }

    protected function createUser(): LockableAdminUserInterface
    {
        return new class implements LockableAdminUserInterface {
            protected int $failedLoginAttempts = 0;

            protected ?\DateTimeImmutable $lastFailedLoginAt = null;

            protected ?\DateTimeImmutable $lockedAt = null;

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
        };
    }

    protected function fixedClock(string $datetime): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable($datetime));

        return $clock;
    }
}
