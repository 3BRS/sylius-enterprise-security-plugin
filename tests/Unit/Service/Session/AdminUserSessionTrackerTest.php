<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Session;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionTracker;
use ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\GeoIpLookupInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\GeoIpResult;

#[CoversClass(AdminUserSessionTracker::class)]
class AdminUserSessionTrackerTest extends TestCase
{
    public function testTrackPersistsNewSessionWithGeoIpAndReturnsIt(): void
    {
        $repository = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $geo = $this->createStub(GeoIpLookupInterface::class);
        $geo->method('lookup')->willReturn(new GeoIpResult('CZ', 'Prague'));

        $user = $this->createStub(AdminUserInterface::class);

        $tracker = new AdminUserSessionTracker($repository, $em, $geo, $this->fixedClock('2026-04-30 10:00:00'));
        $session = $tracker->track($user, 'sess-1', 'Mozilla/5.0', '1.2.3.4');

        self::assertSame('sess-1', $session->getSessionId());
        self::assertSame('CZ', $session->getCountry());
        self::assertSame('Prague', $session->getCity());
    }

    public function testTrackReturnsExistingSessionWhenSessionIdAlreadyTracked(): void
    {
        $existing = new AdminUserSession();
        $existing->setSessionId('sess-1');

        $repository = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $tracker = new AdminUserSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 10:00:00'),
        );
        $session = $tracker->track($this->createStub(AdminUserInterface::class), 'sess-1', null, null);

        self::assertSame($existing, $session);
    }

    public function testTouchUpdatesLastActivityAfterThrottleWindow(): void
    {
        $session = new AdminUserSession();
        $session->setSessionId('sess-1');
        $session->setLastActivityAt(new \DateTimeImmutable('2026-04-30 09:00:00'));

        $repository = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn($session);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $tracker = new AdminUserSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 09:05:00'),
        );
        $tracker->touch('sess-1');

        self::assertEquals(new \DateTimeImmutable('2026-04-30 09:05:00'), $session->getLastActivityAt());
    }

    public function testTouchSkipsUpdateInsideThrottleWindow(): void
    {
        $session = new AdminUserSession();
        $session->setSessionId('sess-1');
        $session->setLastActivityAt(new \DateTimeImmutable('2026-04-30 09:00:00'));

        $repository = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn($session);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $tracker = new AdminUserSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 09:00:30'),
        );
        $tracker->touch('sess-1');
    }

    public function testTouchSkipsRevokedSession(): void
    {
        $session = new AdminUserSession();
        $session->setSessionId('sess-1');
        $session->setLastActivityAt(new \DateTimeImmutable('2026-04-30 09:00:00'));
        $session->setRevokedAt(new \DateTimeImmutable('2026-04-30 09:01:00'));

        $repository = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn($session);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $tracker = new AdminUserSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 10:00:00'),
        );
        $tracker->touch('sess-1');
    }

    public function testRevokeSetsRevokedAt(): void
    {
        $session = new AdminUserSession();
        $session->setSessionId('sess-1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $tracker = new AdminUserSessionTracker(
            $this->createStub(AdminUserSessionRepositoryInterface::class),
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 10:00:00'),
        );
        $tracker->revoke($session);

        self::assertEquals(new \DateTimeImmutable('2026-04-30 10:00:00'), $session->getRevokedAt());
    }

    public function testRevokeOthersLeavesCurrentSessionUntouched(): void
    {
        $current = new AdminUserSession();
        $current->setSessionId('sess-current');
        $other = new AdminUserSession();
        $other->setSessionId('sess-other');
        $expired = new AdminUserSession();
        $expired->setSessionId('sess-expired');
        $expired->setLastActivityAt(new \DateTimeImmutable('2026-04-28 10:00:00'));

        $repository = $this->createMock(AdminUserSessionRepositoryInterface::class);
        $repository->expects(self::never())->method('findActiveForAdminUser');
        $repository->method('findUnrevokedForAdminUser')->willReturn([$current, $other, $expired]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $tracker = new AdminUserSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 10:00:00'),
        );
        $tracker->revokeOthers('sess-current', $this->createStub(AdminUserInterface::class));

        self::assertNull($current->getRevokedAt());
        self::assertNotNull($other->getRevokedAt());
        self::assertNotNull($expired->getRevokedAt());
    }

    public function testMoveSessionGivesTheSessionTheNewId(): void
    {
        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getId')->willReturn(7);
        $session = new AdminUserSession();
        $session->setAdminUser($user);
        $session->setSessionId('previous-id');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $this->makeMoveTracker($session, null, $em)->moveSession('previous-id', 'new-id', $user);

        self::assertSame('new-id', $session->getSessionId());
    }

    public function testMoveSessionLeavesTheSessionOfAnotherUser(): void
    {
        $owner = $this->createStub(AdminUserInterface::class);
        $owner->method('getId')->willReturn(7);
        $session = new AdminUserSession();
        $session->setAdminUser($owner);
        $session->setSessionId('previous-id');
        $otherUser = $this->createStub(AdminUserInterface::class);
        $otherUser->method('getId')->willReturn(8);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->makeMoveTracker($session, null, $em)->moveSession('previous-id', 'new-id', $otherUser);

        self::assertSame('previous-id', $session->getSessionId());
    }

    public function testMoveSessionLeavesARevokedSession(): void
    {
        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getId')->willReturn(7);
        $session = new AdminUserSession();
        $session->setAdminUser($user);
        $session->setSessionId('previous-id');
        $session->setRevokedAt(new \DateTimeImmutable('2026-04-30 09:00:00'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->makeMoveTracker($session, null, $em)->moveSession('previous-id', 'new-id', $user);

        self::assertSame('previous-id', $session->getSessionId());
    }

    public function testMoveSessionKeepsTheSessionWhenTheNewIdIsRecordedAlready(): void
    {
        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getId')->willReturn(7);
        $session = new AdminUserSession();
        $session->setAdminUser($user);
        $session->setSessionId('previous-id');
        $recorded = new AdminUserSession();
        $recorded->setSessionId('new-id');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->makeMoveTracker($session, $recorded, $em)->moveSession('previous-id', 'new-id', $user);

        self::assertSame('previous-id', $session->getSessionId());
    }

    protected function makeMoveTracker(AdminUserSession $previous, ?AdminUserSession $recorded, EntityManagerInterface $em): AdminUserSessionTracker
    {
        $repository = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturnMap([
            ['previous-id', $previous],
            ['new-id', $recorded],
        ]);

        return new AdminUserSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 10:00:00'),
        );
    }

    protected function fixedClock(string $datetime): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable($datetime));

        return $clock;
    }
}
