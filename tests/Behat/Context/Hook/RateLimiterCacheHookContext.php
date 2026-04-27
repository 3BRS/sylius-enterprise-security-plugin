<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Hook;

use Behat\Behat\Context\Context;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Clears the Symfony Rate Limiter cache pool before every Behat scenario.
 *
 * The plugin auto-registers `framework.rate_limiter.three_brs_*` services that share
 * `cache.rate_limiter` (filesystem-backed by default). Without this hook, attempts
 * accumulate across scenarios — once a limit is hit, every later scenario that
 * touches the same throttled endpoint gets HTTP 429 and unrelated tests fail.
 */
class RateLimiterCacheHookContext implements Context
{
    public function __construct(
        protected CacheItemPoolInterface $rateLimiterCachePool,
    ) {
    }

    /** @BeforeScenario */
    public function clearRateLimiterCache(): void
    {
        $this->rateLimiterCachePool->clear();
    }
}
