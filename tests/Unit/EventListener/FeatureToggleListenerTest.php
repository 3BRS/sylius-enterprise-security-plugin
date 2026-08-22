<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Yaml\Yaml;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\FeatureToggleListener;

/**
 * Security Settings offers a switch for these features and the endpoints behind
 * them read a container parameter, so turning one off emptied the menu and left
 * every URL working.
 */
#[CoversClass(FeatureToggleListener::class)]
class FeatureToggleListenerTest extends TestCase
{
    public function testASwitchedOffFeatureIsNotReachableByItsUrl(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->listen('three_brs_shop_passkey_index', enabled: false);
    }

    public function testASwitchedOnFeatureIsLeftAlone(): void
    {
        $this->listen('three_brs_shop_passkey_index', enabled: true);

        $this->expectNotToPerformAssertions();
    }

    public function testARouteOutsideTheMapIsLeftAlone(): void
    {
        $this->listen('sylius_shop_homepage', enabled: false);

        $this->expectNotToPerformAssertions();
    }

    public function testEachEndpointAsksAboutItsOwnGroup(): void
    {
        $asked = [];
        $toggle = $this->createStub(FeatureToggleInterface::class);
        $toggle->method('isEnabled')->willReturnCallback(
            function (string $feature, SettingsScope $scope) use (&$asked): bool {
                $asked[] = $feature . '/' . $scope->value;

                return true;
            },
        );

        $listener = new FeatureToggleListener($toggle);
        $listener->onKernelController($this->makeEvent('three_brs_admin_sessions'));
        $listener->onKernelController($this->makeEvent('three_brs_shop_sessions'));

        self::assertSame(['session_management/admin', 'session_management/customer'], $asked);
    }

    /**
     * Every route the map names has to exist, or the gate silently guards nothing.
     *
     * @return iterable<string, array{string}>
     */
    public static function mappedRouteProvider(): iterable
    {
        /** @var array<string, array{string, SettingsScope}> $map */
        $map = (new \ReflectionClass(FeatureToggleListener::class))->getConstant('ROUTE_MAP');

        foreach (array_keys($map) as $route) {
            yield $route => [$route];
        }
    }

    #[DataProvider('mappedRouteProvider')]
    public function testAMappedRouteIsDeclared(string $route): void
    {
        /** @var array<string, mixed> $routes */
        $routes = Yaml::parseFile(\dirname(__DIR__, 3) . '/src/Resources/config/routes.yaml');

        self::assertArrayHasKey($route, $routes);
    }

    protected function listen(string $route, bool $enabled): void
    {
        $toggle = $this->createStub(FeatureToggleInterface::class);
        $toggle->method('isEnabled')->willReturn($enabled);

        (new FeatureToggleListener($toggle))->onKernelController($this->makeEvent($route));
    }

    protected function makeEvent(string $route): ControllerEvent
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);

        return new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn (): Response => new Response(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
