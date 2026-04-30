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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpLookupInterface;

#[CoversClass(AdminUserSessionTracker::class)]
class AdminUserSessionTrackerTest extends TestCase
{
    public function testTrackPersistsNewSession(): void
    {
        $repository = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $geo = $this->createStub(GeoIpLookupInterface::class);
        $geo->method('lookup')->willReturn(null);

        $tracker = new AdminUserSessionTracker($repository, $em, $geo, $this->fixedClock('2026-04-30 10:00:00'));
        $session = $tracker->track($this->createStub(AdminUserInterface::class), 'sess-1', 'UA', '1.2.3.4');

        self::assertSame('sess-1', $session->getSessionId());
        self::assertSame('1.2.3.4', $session->getIpAddress());
    }

    public function testRevokeOthersLeavesCurrentUntouched(): void
    {
        $current = new AdminUserSession();
        $current->setSessionId('admin-current');
        $other = new AdminUserSession();
        $other->setSessionId('admin-other');

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
        $tracker->revokeOthers('admin-current', $this->createStub(AdminUserInterface::class));

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
