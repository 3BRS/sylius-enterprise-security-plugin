<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Functional;

use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;
use ThreeBRS\EnterpriseSecurityBundle\Settings\Defaults\SettingsDefaultsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\RateLimitListener;

/**
 * Section 5.10 of docs/manual-test-plan.md: every throttled endpoint is a row in
 * RateLimitListener::ROUTE_MAP, and the listener only ever acts on a request whose
 * `_route` matches a key of that map exactly.
 *
 * That makes the map's own failure mode silent. A route renamed upstream, or a key
 * misspelled here, matches nothing: the limiter never runs for that endpoint, the
 * request is answered normally, and no test goes red — the same shape as a
 * .gitattributes rule that matches no file. T49 covers the login rows by driving a
 * browser; the seven other rows are checked here against sources outside the map,
 * because the router and the settings defaults are what the map has to agree with.
 *
 * Behaviour lives in the Behat scenarios tagged @T49, @T50 and @T51. This file
 * asks only whether each row can fire at all.
 */
class RateLimitRouteMatrixTest extends KernelTestCase
{
    /**
     * The endpoints §5.10 of the plan expects to be throttled, as (group, action)
     * pairs. The tests below enumerate the map, so they agree with whatever it
     * happens to say; a row deleted from it would take its own coverage with it.
     * This list is the expectation from outside that notices the deletion.
     *
     * @var list<array{string, string}>
     */
    protected const ENDPOINTS_THE_PLAN_REQUIRES = [
        ['customer', 'login'],
        ['customer', 'password_reset'],
        ['customer', 'register'],
        ['customer', 'magic_link'],
        ['admin', 'login'],
        ['admin', 'password_reset'],
        ['admin', 'magic_link'],
    ];

    /**
     * Every setting a limiter needs in order to run. `enabled` decides whether the
     * guard consumes anything at all, and a limiter with no `limit` or `interval`
     * cannot be built.
     *
     * @var list<string>
     */
    protected const SETTINGS_A_LIMITER_NEEDS = ['enabled', 'limit', 'interval'];

    protected RouterInterface $router;

    protected SettingsDefaultsProviderInterface $defaults;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel(['environment' => 'test', 'debug' => true]);
        $container = self::getContainer();

        /** @var RouterInterface $router */
        $router = $container->get('router');
        $this->router = $router;

        /** @var SettingsDefaultsProviderInterface $defaults */
        $defaults = $container->get(SettingsDefaultsProviderInterface::class);
        $this->defaults = $defaults;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Booting the Sylius kernel leaves an exception handler on the stack.
        restore_exception_handler();
    }

    /**
     * The one that matters most: a key naming no route throttles nothing, and says
     * so to nobody.
     */
    public function testEveryThrottledRouteIsARouteTheApplicationHas(): void
    {
        $collection = $this->router->getRouteCollection();

        foreach (array_keys($this->routeMap()) as $route) {
            self::assertNotNull(
                $collection->get($route),
                sprintf(
                    'ROUTE_MAP throttles "%s", which the router does not know. The row matches no request, '
                    . 'so that endpoint is not rate limited at all.',
                    $route,
                ),
            );
        }
    }

    /**
     * Where a throttled request is sent once it has been refused. A name no route
     * answers to turns the refusal itself into an error, and only under load.
     */
    public function testEveryFallbackRouteIsARouteTheApplicationHas(): void
    {
        $collection = $this->router->getRouteCollection();

        foreach ($this->routeMap() as $route => [, , $fallbackRoute]) {
            self::assertNotNull(
                $collection->get($fallbackRoute),
                sprintf(
                    'Throttling "%s" redirects to "%s", which the router does not know.',
                    $route,
                    $fallbackRoute,
                ),
            );
        }
    }

    /**
     * The guard reads `rate_limit.<action>.*` in the scope the group names. An
     * action with nothing behind it cannot be enabled, so the guard returns before
     * consuming and the endpoint runs unthrottled.
     */
    public function testEveryThrottledActionHasALimiterConfiguredBehindIt(): void
    {
        $defaults = $this->defaults->all();

        foreach ($this->routeMap() as $route => [$group, $action]) {
            $scope = $this->scopeFor($group, $route);

            self::assertArrayHasKey(
                $scope->value,
                $defaults,
                sprintf('"%s" is throttled in the "%s" scope, which the defaults do not offer.', $route, $scope->value),
            );

            foreach (self::SETTINGS_A_LIMITER_NEEDS as $setting) {
                $path = sprintf('rate_limit.%s.%s', $action, $setting);

                self::assertArrayHasKey(
                    $path,
                    $defaults[$scope->value],
                    sprintf(
                        '"%s" is throttled as %s.%s, but %s has no default in the %s scope — '
                        . 'the guard has no limiter to build.',
                        $route,
                        $group,
                        $action,
                        $path,
                        $scope->value,
                    ),
                );
            }
        }
    }

    public function testEveryEndpointThePlanRequiresIsInTheMap(): void
    {
        $pairs = [];
        foreach ($this->routeMap() as [$group, $action]) {
            $pairs[$group . '.' . $action] = true;
        }

        foreach (self::ENDPOINTS_THE_PLAN_REQUIRES as [$group, $action]) {
            self::assertArrayHasKey(
                $group . '.' . $action,
                $pairs,
                sprintf(
                    'No route is throttled as %s.%s. Section 5.10 of the manual test plan expects that '
                    . 'endpoint to be rate limited.',
                    $group,
                    $action,
                ),
            );
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3?: string}>
     */
    protected function routeMap(): array
    {
        /** @var array<string, array{0: string, 1: string, 2: string, 3?: string}> $map */
        $map = (new ReflectionClass(RateLimitListener::class))->getConstant('ROUTE_MAP');
        self::assertNotEmpty($map, 'The listener throttles no routes — the matrix would assert nothing.');

        return $map;
    }

    protected function scopeFor(string $group, string $route): SettingsScope
    {
        $scope = SettingsScope::tryFrom($group);
        self::assertNotNull($scope, sprintf('"%s" is throttled for group "%s", which is not a settings scope.', $route, $group));

        return $scope;
    }
}
