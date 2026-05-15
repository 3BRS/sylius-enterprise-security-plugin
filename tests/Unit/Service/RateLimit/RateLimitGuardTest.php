<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\RateLimit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RateLimit\RateLimitGuard;

#[CoversClass(RateLimitGuard::class)]
class RateLimitGuardTest extends TestCase
{
    public function testIsEnabledReturnsTrueOnlyForExplicitlyEnabledKey(): void
    {
        $guard = new RateLimitGuard(
            $this->createStub(ContainerInterface::class),
            ['customer.login' => true, 'admin.login' => false],
        );

        self::assertTrue($guard->isEnabled('customer', 'login'));
        self::assertFalse($guard->isEnabled('admin', 'login'));
        self::assertFalse($guard->isEnabled('customer', 'unknown'));
    }

    public function testConsumeNoOpsWhenDisabled(): void
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->expects(self::never())->method('has');

        $guard = new RateLimitGuard($locator, ['customer.login' => false]);

        $guard->consume(Request::create('/login', 'POST'), 'customer', 'login');
    }

    public function testConsumeNoOpsWhenLimiterServiceMissing(): void
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->with('limiter.three_brs_customer_login')->willReturn(false);
        $locator->expects(self::never())->method('get');

        $guard = new RateLimitGuard($locator, ['customer.login' => true]);

        $guard->consume(Request::create('/login', 'POST'), 'customer', 'login');
    }

    public function testConsumeAllowsRequestWhenLimitNotExceeded(): void
    {
        $factory = $this->buildFactory(limit: 5);

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->with('limiter.three_brs_customer_login')->willReturn(true);
        $locator->method('get')->with('limiter.three_brs_customer_login')->willReturn($factory);

        $guard = new RateLimitGuard($locator, ['customer.login' => true]);

        // Limit is 5, single consume must pass without throwing.
        $guard->consume(Request::create('/login', 'POST'), 'customer', 'login', 'user@example.com');

        // No assertions needed — absence of exception is the assertion.
        self::assertTrue(true);
    }

    public function testConsumeThrowsWhenLimitExceeded(): void
    {
        $factory = $this->buildFactory(limit: 1);

        $locator = $this->createStub(ContainerInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);

        $guard = new RateLimitGuard($locator, ['customer.login' => true]);

        // Same key both times so the bucket is shared.
        $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.42']);

        // First call consumes the only allowed token.
        $guard->consume($request, 'customer', 'login');

        // Second call must exceed the limit.
        $this->expectException(TooManyRequestsHttpException::class);
        $guard->consume($request, 'customer', 'login');
    }

    protected function buildFactory(int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory(
            [
                'id' => 'test_limiter',
                'policy' => 'fixed_window',
                'limit' => $limit,
                'interval' => '15 minutes',
            ],
            new InMemoryStorage(),
        );
    }
}
