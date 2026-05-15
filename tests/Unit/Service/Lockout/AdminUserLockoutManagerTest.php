<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Lockout;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\AdminUserLockoutManager;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockoutPolicy;
use ThreeBRS\EnterpriseSecurityBundle\RateLimit\RateLimitGuardInterface;

/** @internal */
interface TestLockableAdminUser extends LockableAdminUserInterface, AdminUserInterface
{
}

#[CoversClass(AdminUserLockoutManager::class)]
class AdminUserLockoutManagerTest extends TestCase
{
    public function testRecordFailureNoOpsWhenPolicyDisabled(): void
    {
        $policy = new LockoutPolicy(enabled: false, maxAttempts: 3, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'), $this->createStub(RateLimitGuardInterface::class));
        $user = $this->createUser();

        $manager->recordFailure($user);

        self::assertSame(0, $user->getFailedLoginAttempts());
    }

    public function testRecordFailureIncrementsCounter(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'), $this->createStub(RateLimitGuardInterface::class));
        $user = $this->createUser();

        $manager->recordFailure($user);

        self::assertSame(1, $user->getFailedLoginAttempts());
        self::assertNull($user->getLockedAt());
    }

    public function testRecordFailureLocksAccountAtThreshold(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, autoUnlockAfter: 1800);
        $em = $this->createStub(EntityManagerInterface::class);

        $now = new \DateTimeImmutable('2026-04-27 10:00:00');
        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'), $this->createStub(RateLimitGuardInterface::class));
        $user = $this->createUser();
        $user->setFailedLoginAttempts(2);

        $manager->recordFailure($user);

        self::assertSame(3, $user->getFailedLoginAttempts());
        self::assertEquals($now, $user->getLockedAt());
        self::assertEquals($now->modify('+1800 seconds'), $user->getLockoutUntil());
    }

    public function testRecordFailureManualOnlyKeepsLockoutUntilNull(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 2, autoUnlockAfter: null);
        $em = $this->createStub(EntityManagerInterface::class);

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'), $this->createStub(RateLimitGuardInterface::class));
        $user = $this->createUser();
        $user->setFailedLoginAttempts(1);

        $manager->recordFailure($user);

        self::assertNotNull($user->getLockedAt());
        self::assertNull($user->getLockoutUntil());
    }

    public function testIsLockedReturnsFalseWhenPolicyDisabled(): void
    {
        $policy = new LockoutPolicy(enabled: false, maxAttempts: 3, autoUnlockAfter: 60);
        $manager = new AdminUserLockoutManager($policy, $this->createStub(EntityManagerInterface::class), $this->fixedClock('2026-04-27 10:00:00'), $this->createStub(RateLimitGuardInterface::class));
        $user = $this->createUser();
        $user->setLockedAt(new \DateTimeImmutable('2026-04-27 09:00:00'));

        self::assertFalse($manager->isLocked($user));
    }

    public function testIsLockedReturnsFalseWhenLockoutUntilIsInThePastAndDoesNotMutate(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:30:00'), $this->createStub(RateLimitGuardInterface::class));
        $user = $this->createUser();
        $lockedAt = new \DateTimeImmutable('2026-04-27 10:00:00');
        $lockoutUntil = new \DateTimeImmutable('2026-04-27 10:15:00');
        $user->setLockedAt($lockedAt);
        $user->setLockoutUntil($lockoutUntil);
        $user->setFailedLoginAttempts(3);

        self::assertFalse($manager->isLocked($user));
        // Query is non-mutating — DB state remains "stale-locked" until recordFailure /
        // recordSuccess / unlock cleans it up.
        self::assertSame($lockedAt, $user->getLockedAt());
        self::assertSame($lockoutUntil, $user->getLockoutUntil());
        self::assertSame(3, $user->getFailedLoginAttempts());
    }

    public function testRecordFailureRestartsCounterAfterExpiredLockout(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, autoUnlockAfter: 60);
        $em = $this->createStub(EntityManagerInterface::class);

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:30:00'), $this->createStub(RateLimitGuardInterface::class));
        $user = $this->createUser();
        $user->setLockedAt(new \DateTimeImmutable('2026-04-27 10:00:00'));
        $user->setLockoutUntil(new \DateTimeImmutable('2026-04-27 10:15:00'));
        $user->setFailedLoginAttempts(3);

        $manager->recordFailure($user);

        // The expired lockout was reset to a clean slate, then this single failure
        // was recorded — so the counter is back to 1, not 4.
        self::assertSame(1, $user->getFailedLoginAttempts());
        self::assertNull($user->getLockedAt());
        self::assertNull($user->getLockoutUntil());
    }

    public function testIsLockedReturnsTrueWhenLockoutUntilIsInTheFuture(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, autoUnlockAfter: 60);
        $manager = new AdminUserLockoutManager($policy, $this->createStub(EntityManagerInterface::class), $this->fixedClock('2026-04-27 10:30:00'), $this->createStub(RateLimitGuardInterface::class));
        $user = $this->createUser();
        $user->setLockedAt(new \DateTimeImmutable('2026-04-27 10:00:00'));
        $user->setLockoutUntil(new \DateTimeImmutable('2026-04-27 11:00:00'));

        self::assertTrue($manager->isLocked($user));
    }

    public function testRecordSuccessResetsLockoutState(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'), $this->createStub(RateLimitGuardInterface::class));
        $user = $this->createUser();
        $user->setFailedLoginAttempts(2);
        $user->setLastFailedLoginAt(new \DateTimeImmutable('2026-04-27 09:55:00'));

        $manager->recordSuccess($user);

        self::assertSame(0, $user->getFailedLoginAttempts());
        self::assertNull($user->getLastFailedLoginAt());
    }

    public function testUnlockClearsAllLockoutState(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, autoUnlockAfter: 60);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'), $this->createStub(RateLimitGuardInterface::class));
        $user = $this->createUser();
        $user->setFailedLoginAttempts(5);
        $user->setLockedAt(new \DateTimeImmutable());
        $user->setLockoutUntil(new \DateTimeImmutable());

        $manager->unlock($user);

        self::assertSame(0, $user->getFailedLoginAttempts());
        self::assertNull($user->getLockedAt());
        self::assertNull($user->getLockoutUntil());
    }

    public function testUnlockResetsRateLimitForBothUsernameAndEmail(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, autoUnlockAfter: 60);
        $em = $this->createStub(EntityManagerInterface::class);

        $user = $this->createStub(TestLockableAdminUser::class);
        $user->method('getUsername')->willReturn('admin-user');
        $user->method('getEmail')->willReturn('admin@example.com');

        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);
        $matcher = self::exactly(2);
        $rateLimitGuard->expects($matcher)
            ->method('reset')
            ->willReturnCallback(function (string $group, string $action, string $userIdentifier) use ($matcher) {
                self::assertSame('admin', $group);
                self::assertSame('login', $action);
                match ($matcher->numberOfInvocations()) {
                    1 => self::assertSame('admin-user', $userIdentifier),
                    2 => self::assertSame('admin@example.com', $userIdentifier),
                    default => self::fail('Unexpected reset call.'),
                };
            })
        ;

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'), $rateLimitGuard);
        $manager->unlock($user);
    }

    public function testUnlockResetsRateLimitOnlyOnceWhenUsernameEqualsEmail(): void
    {
        $policy = new LockoutPolicy(enabled: true, maxAttempts: 3, autoUnlockAfter: 60);
        $em = $this->createStub(EntityManagerInterface::class);

        $user = $this->createStub(TestLockableAdminUser::class);
        $user->method('getUsername')->willReturn('admin@example.com');
        $user->method('getEmail')->willReturn('admin@example.com');

        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);
        $rateLimitGuard->expects(self::once())
            ->method('reset')
            ->with('admin', 'login', 'admin@example.com')
        ;

        $manager = new AdminUserLockoutManager($policy, $em, $this->fixedClock('2026-04-27 10:00:00'), $rateLimitGuard);
        $manager->unlock($user);
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
