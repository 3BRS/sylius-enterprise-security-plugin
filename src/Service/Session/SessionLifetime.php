<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Psr\Clock\ClockInterface;

class SessionLifetime implements SessionLifetimeInterface
{
    /**
     * AbstractSessionTracker records activity at most once per this many seconds, so the last
     * request of a session can be this much newer than its recorded activity.
     */
    protected const ACTIVITY_TOUCH_THROTTLE_SECONDS = 60;

    public function __construct(
        protected ClockInterface $clock,
        protected ?int $lifetime,
    ) {
    }

    public function getActiveSince(): \DateTimeImmutable
    {
        $seconds = $this->getLifetime() + static::ACTIVITY_TOUCH_THROTTLE_SECONDS;

        return $this->clock->now()->sub(new \DateInterval(sprintf('PT%dS', $seconds)));
    }

    protected function getLifetime(): int
    {
        // Read per call: the session storage applies framework.session.gc_maxlifetime when it starts.
        return $this->lifetime ?? (int) ini_get('session.gc_maxlifetime');
    }
}
