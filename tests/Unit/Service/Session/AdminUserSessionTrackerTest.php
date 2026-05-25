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

        $repository = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $repository->method('findActiveForAdminUser')->willReturn([$current, $other]);

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
    }

    protected function fixedClock(string $datetime): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable($datetime));

        return $clock;
    }
}
