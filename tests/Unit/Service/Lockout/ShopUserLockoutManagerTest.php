<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Lockout;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\LockoutPolicy;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\ShopUserLockoutManager;

#[CoversClass(ShopUserLockoutManager::class)]
class ShopUserLockoutManagerTest extends TestCase
{
    public function testRecordFailureNoOpsWhenPolicyDisabled(): void
    {
        $policy = new LockoutPolicy(enabled: false, maxAttempts: 3, lockoutDuration: 60, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $manager = new ShopUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'));
        $user = $this->createUser();

        $manager->recordFailure($user);

        self::assertSame(0, $user->getFailedLoginAttempts());
    }

    public function testRecordFailureIncrementsCounter(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, lockoutDuration: 60, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new ShopUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'));
        $user = $this->createUser();

        $manager->recordFailure($user);

        self::assertSame(1, $user->getFailedLoginAttempts());
        self::assertNull($user->getLockedAt());
    }

    public function testRecordFailureLocksAccountAtThreshold(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, lockoutDuration: 60, autoUnlockAfter: 90);
        $em = $this->createStub(EntityManagerInterface::class);

        $now = new \DateTimeImmutable('2026-04-27 10:00:00');
        $manager = new ShopUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'));
        $user = $this->createUser();
        $user->setFailedLoginAttempts(2);

        $manager->recordFailure($user);

        self::assertSame(3, $user->getFailedLoginAttempts());
        self::assertEquals($now, $user->getLockedAt());
        self::assertEquals($now->modify('+90 seconds'), $user->getLockoutUntil());
    }

    public function testRecordFailureManualOnlyKeepsLockoutUntilNull(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 2, lockoutDuration: 60, autoUnlockAfter: null);
        $em = $this->createStub(EntityManagerInterface::class);

        $manager = new ShopUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'));
        $user = $this->createUser();
        $user->setFailedLoginAttempts(1);

        $manager->recordFailure($user);

        self::assertNotNull($user->getLockedAt());
        self::assertNull($user->getLockoutUntil());
    }

    public function testIsLockedReturnsFalseWhenPolicyDisabled(): void
    {
        $policy = new LockoutPolicy(enabled: false, maxAttempts: 3, lockoutDuration: 60, autoUnlockAfter: 60);
        $manager = new ShopUserLockoutManager($policy, $this->createStub(EntityManagerInterface::class), $this->fixedClock('2026-04-27 10:00:00'));
        $user = $this->createUser();
        $user->setLockedAt(new \DateTimeImmutable('2026-04-27 09:00:00'));

        self::assertFalse($manager->isLocked($user));
    }

    public function testIsLockedAutoUnlocksWhenLockoutUntilIsInThePast(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, lockoutDuration: 60, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new ShopUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:30:00'));
        $user = $this->createUser();
        $user->setLockedAt(new \DateTimeImmutable('2026-04-27 10:00:00'));
        $user->setLockoutUntil(new \DateTimeImmutable('2026-04-27 10:15:00'));
        $user->setFailedLoginAttempts(3);

        self::assertFalse($manager->isLocked($user));
        self::assertNull($user->getLockedAt());
        self::assertSame(0, $user->getFailedLoginAttempts());
    }

    public function testIsLockedReturnsTrueWhenLockoutUntilIsInTheFuture(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, lockoutDuration: 60, autoUnlockAfter: 60);
        $manager = new ShopUserLockoutManager($policy, $this->createStub(EntityManagerInterface::class), $this->fixedClock('2026-04-27 10:30:00'));
        $user = $this->createUser();
        $user->setLockedAt(new \DateTimeImmutable('2026-04-27 10:00:00'));
        $user->setLockoutUntil(new \DateTimeImmutable('2026-04-27 11:00:00'));

        self::assertTrue($manager->isLocked($user));
    }

    public function testRecordSuccessResetsLockoutState(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, lockoutDuration: 60, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new ShopUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'));
        $user = $this->createUser();
        $user->setFailedLoginAttempts(2);
        $user->setLastFailedLoginAt(new \DateTimeImmutable('2026-04-27 09:55:00'));

        $manager->recordSuccess($user);

        self::assertSame(0, $user->getFailedLoginAttempts());
        self::assertNull($user->getLastFailedLoginAt());
    }

    public function testUnlockClearsAllLockoutState(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, lockoutDuration: 60, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new ShopUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'));
        $user = $this->createUser();
        $user->setFailedLoginAttempts(5);
        $user->setLockedAt(new \DateTimeImmutable());
        $user->setLockoutUntil(new \DateTimeImmutable());

        $manager->unlock($user);

        self::assertSame(0, $user->getFailedLoginAttempts());
        self::assertNull($user->getLockedAt());
        self::assertNull($user->getLockoutUntil());
    }

    protected function createUser(): LockableShopUserInterface
    {
        return new class implements LockableShopUserInterface {
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
