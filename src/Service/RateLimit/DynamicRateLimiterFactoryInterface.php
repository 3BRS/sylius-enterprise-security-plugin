<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RateLimit;

use Symfony\Component\RateLimiter\RateLimit;

interface DynamicRateLimiterFactoryInterface
{
    public function isEnabled(string $group, string $action): bool;

    public function consume(string $group, string $action, string $key): RateLimit;

    public function reset(string $group, string $action, string $key): void;
}
