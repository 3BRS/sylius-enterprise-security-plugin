<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RateLimit;

use Symfony\Component\HttpFoundation\Request;

interface RateLimitGuardInterface
{
    /** @throws \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException */
    public function consume(Request $request, string $group, string $action, ?string $userIdentifier = null): void;

    public function isEnabled(string $group, string $action): bool;
}
