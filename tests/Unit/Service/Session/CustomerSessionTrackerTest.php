<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Session;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTracker;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpLookupInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpResult;

#[CoversClass(CustomerSessionTracker::class)]
class CustomerSessionTrackerTest extends TestCase
{
    public function testTrackPersistsNewSessionWithGeoIpAndReturnsIt(): void
    {
        $repository = $this->createStub(CustomerSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $geo = $this->createStub(GeoIpLookupInterface::class);
        $geo->method('lookup')->willReturn(new GeoIpResult('US', 'New York'));

        $user = $this->createStub(ShopUserInterface::class);

        $tracker = new CustomerSessionTracker($repository, $em, $geo, $this->fixedClock('2026-04-30 10:00:00'));
        $session = $tracker->track($user, 'sess-1', 'Mozilla/5.0', '8.8.8.8');

        self::assertSame('sess-1', $session->getSessionId());
        self::assertSame('US', $session->getCountry());
        self::assertSame('New York', $session->getCity());
    }

    public function testTrackReturnsExistingSessionWhenSessionIdAlreadyTracked(): void
    {
        $existing = new CustomerSession();
        $existing->setSessionId('sess-1');

        $repository = $this->createStub(CustomerSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $tracker = new CustomerSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 10:00:00'),
        );
        $session = $tracker->track($this->createStub(ShopUserInterface::class), 'sess-1', null, null);

        self::assertSame($existing, $session);
    }

    public function testTouchUpdatesLastActivityAfterThrottleWindow(): void
    {
        $session = new CustomerSession();
        $session->setSessionId('sess-1');
        $session->setLastActivityAt(new \DateTimeImmutable('2026-04-30 09:00:00'));

        $repository = $this->createStub(CustomerSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn($session);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $tracker = new CustomerSessionTracker(
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
        $session = new CustomerSession();
        $session->setSessionId('sess-1');
        $session->setLastActivityAt(new \DateTimeImmutable('2026-04-30 09:00:00'));

        $repository = $this->createStub(CustomerSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn($session);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $tracker = new CustomerSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 09:00:30'),
        );
        $tracker->touch('sess-1');
    }

    public function testTouchSkipsRevokedSession(): void
    {
        $session = new CustomerSession();
        $session->setSessionId('sess-1');
        $session->setLastActivityAt(new \DateTimeImmutable('2026-04-30 09:00:00'));
        $session->setRevokedAt(new \DateTimeImmutable('2026-04-30 09:01:00'));

        $repository = $this->createStub(CustomerSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn($session);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $tracker = new CustomerSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 10:00:00'),
        );
        $tracker->touch('sess-1');
    }

    public function testRevokeSetsRevokedAt(): void
    {
        $session = new CustomerSession();
        $session->setSessionId('sess-1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $tracker = new CustomerSessionTracker(
            $this->createStub(CustomerSessionRepositoryInterface::class),
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 10:00:00'),
        );
        $tracker->revoke($session);

        self::assertEquals(new \DateTimeImmutable('2026-04-30 10:00:00'), $session->getRevokedAt());
    }

    public function testRevokeOthersLeavesCurrentSessionUntouched(): void
    {
        $current = new CustomerSession();
        $current->setSessionId('sess-current');
        $other = new CustomerSession();
        $other->setSessionId('sess-other');

        $repository = $this->createStub(CustomerSessionRepositoryInterface::class);
        $repository->method('findActiveForShopUser')->willReturn([$current, $other]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $tracker = new CustomerSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 10:00:00'),
        );
        $tracker->revokeOthers('sess-current', $this->createStub(ShopUserInterface::class));

        self::assertNull($current->getRevokedAt());
        self::assertNotNull($other->getRevokedAt());
    }

    public function testRevokeAllRevokesEveryActiveSession(): void
    {
        $a = new CustomerSession();
        $a->setSessionId('sess-a');
        $b = new CustomerSession();
        $b->setSessionId('sess-b');

        $repository = $this->createStub(CustomerSessionRepositoryInterface::class);
        $repository->method('findActiveForShopUser')->willReturn([$a, $b]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $tracker = new CustomerSessionTracker(
            $repository,
            $em,
            $this->createStub(GeoIpLookupInterface::class),
            $this->fixedClock('2026-04-30 10:00:00'),
        );
        $tracker->revokeAll($this->createStub(ShopUserInterface::class));

        self::assertEquals(new \DateTimeImmutable('2026-04-30 10:00:00'), $a->getRevokedAt());
        self::assertEquals(new \DateTimeImmutable('2026-04-30 10:00:00'), $b->getRevokedAt());
    }

    protected function fixedClock(string $datetime): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable($datetime));

        return $clock;
    }
}
