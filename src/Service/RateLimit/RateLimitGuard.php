<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RateLimit;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimitGuard implements RateLimitGuardInterface
{
    /**
     * @param ContainerInterface  $limiterLocator service locator with `limiter.three_brs_<group>_<action>` services
     * @param array<string, bool> $enabledMap     keys formatted as `<group>.<action>` → bool
     */
    public function __construct(
        protected ContainerInterface $limiterLocator,
        protected array $enabledMap,
    ) {
    }

    public function isEnabled(string $group, string $action): bool
    {
        return ($this->enabledMap[$this->mapKey($group, $action)] ?? false) === true;
    }

    public function consume(Request $request, string $group, string $action, ?string $userIdentifier = null): void
    {
        if (!$this->isEnabled($group, $action)) {
            return;
        }

        $limiterId = sprintf('limiter.three_brs_%s_%s', $group, $action);
        if (!$this->limiterLocator->has($limiterId)) {
            return;
        }

        /** @var RateLimiterFactory $factory */
        $factory = $this->limiterLocator->get($limiterId);

        $key = $this->buildKey($request, $userIdentifier);
        $limit = $factory->create($key)->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $limit->getRetryAfter()->getTimestamp() - time(),
                'three_brs.rate_limit.too_many_requests',
            );
        }
    }

    public function reset(string $group, string $action, string $userIdentifier): void
    {
        if (!$this->isEnabled($group, $action)) {
            return;
        }

        $limiterId = sprintf('limiter.three_brs_%s_%s', $group, $action);
        if (!$this->limiterLocator->has($limiterId)) {
            return;
        }

        /** @var RateLimiterFactory $factory */
        $factory = $this->limiterLocator->get($limiterId);
        $factory->create(strtolower($userIdentifier))->reset();
    }

    /**
     * When the route provides a username (login forms), key only on that — gives admin
     * unlock a deterministic key to reset. For routes without a username (register,
     * password reset, magic-link request), key on IP — anti-enumeration / anti-spam.
     */
    protected function buildKey(Request $request, ?string $userIdentifier): string
    {
        if ($userIdentifier !== null && $userIdentifier !== '') {
            return strtolower($userIdentifier);
        }

        return $request->getClientIp() ?? 'unknown';
    }

    protected function mapKey(string $group, string $action): string
    {
        return $group . '.' . $action;
    }
}
