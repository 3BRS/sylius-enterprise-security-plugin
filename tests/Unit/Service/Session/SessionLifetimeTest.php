<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\AbstractSessionTracker;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\SessionLifetime;

#[CoversClass(SessionLifetime::class)]
class SessionLifetimeTest extends TestCase
{
    public function testAConfiguredLifetimeIsExtendedByTheActivityThrottle(): void
    {
        $sessionLifetime = new SessionLifetime($this->fixedClock('2026-10-05 12:00:00'), 3600);

        self::assertEquals(new \DateTimeImmutable('2026-10-05 10:59:00'), $sessionLifetime->getActiveSince());
    }

    public function testWithoutAConfiguredLifetimeTheSessionGarbageCollectionLifetimeApplies(): void
    {
        $sessionLifetime = new SessionLifetime($this->fixedClock('2026-10-05 12:00:00'), null);

        $seconds = (int) ini_get('session.gc_maxlifetime') + 60;
        self::assertEquals(
            (new \DateTimeImmutable('2026-10-05 12:00:00'))->modify(sprintf('-%d seconds', $seconds)),
            $sessionLifetime->getActiveSince(),
        );
    }

    public function testTheMarginCoversHowOftenTheTrackerRecordsActivity(): void
    {
        $throttle = new \ReflectionClassConstant(AbstractSessionTracker::class, 'ACTIVITY_TOUCH_THROTTLE_SECONDS');
        $margin = new \ReflectionClassConstant(SessionLifetime::class, 'ACTIVITY_TOUCH_THROTTLE_SECONDS');

        self::assertGreaterThanOrEqual($throttle->getValue(), $margin->getValue());
    }

    protected function fixedClock(string $datetime): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable($datetime));

        return $clock;
    }
}
