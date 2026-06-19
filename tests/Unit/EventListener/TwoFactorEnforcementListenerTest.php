<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\TwoFactorEnforcementListener;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorEnforcementCheckerInterface;

/** @internal test double: shop user with 2FA support */
interface TestShopUserForEnforcement extends ShopUserInterface, TwoFactorAuthShopUserInterface
{
}

/** @internal test double: admin user with 2FA support */
interface TestAdminUserForEnforcement extends AdminUserInterface, TwoFactorAuthAdminUserInterface
{
}

#[CoversClass(TwoFactorEnforcementListener::class)]
class TwoFactorEnforcementListenerTest extends TestCase
{
    private function createListener(
        TokenStorageInterface $tokenStorage,
        TwoFactorEnforcementCheckerInterface $checker,
        RouterInterface $router,
        string $apiRoute = '/api/v2',
    ): TwoFactorEnforcementListener {
        return new TwoFactorEnforcementListener($tokenStorage, $checker, $router, $apiRoute);
    }

    private function createEvent(string $route = 'some_route', string $path = '/'): RequestEvent
    {
        $request = Request::create($path);
        $request->attributes->set('_route', $route);

        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function tokenStorageWithUser(UserInterface $user): TokenStorageInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        return $tokenStorage;
    }

    private function tokenStorageWithNoToken(): TokenStorageInterface
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        return $tokenStorage;
    }

    public function testDoesNothingWhenNoToken(): void
    {
        $checker = $this->createMock(TwoFactorEnforcementCheckerInterface::class);
        $checker->expects(self::never())->method('shouldEnforceForShopUser');

        $listener = $this->createListener(
            $this->tokenStorageWithNoToken(),
            $checker,
            $this->createStub(RouterInterface::class),
        );

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingOnExcludedRoute(): void
    {
        $checker = $this->createMock(TwoFactorEnforcementCheckerInterface::class);
        $checker->expects(self::never())->method('shouldEnforceForShopUser');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUserForEnforcement::class)),
            $checker,
            $this->createStub(RouterInterface::class),
        );

        $event = $this->createEvent('three_brs_shop_two_factor_setup');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingOnInternalRoute(): void
    {
        $checker = $this->createMock(TwoFactorEnforcementCheckerInterface::class);
        $checker->expects(self::never())->method('shouldEnforceForShopUser');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUserForEnforcement::class)),
            $checker,
            $this->createStub(RouterInterface::class),
        );

        $event = $this->createEvent('_wdt');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRedirectsShopUserWhenEnforced(): void
    {
        $checker = $this->createStub(TwoFactorEnforcementCheckerInterface::class);
        $checker->method('shouldEnforceForShopUser')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->with('three_brs_shop_two_factor_setup')
            ->willReturn('/en_US/account/two-factor/setup');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUserForEnforcement::class)),
            $checker,
            $router,
        );

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame('/en_US/account/two-factor/setup', $event->getResponse()->getTargetUrl());
    }

    public function testDoesNotRedirectShopUserWhenNotEnforced(): void
    {
        $checker = $this->createStub(TwoFactorEnforcementCheckerInterface::class);
        $checker->method('shouldEnforceForShopUser')->willReturn(false);

        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::never())->method('generate');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUserForEnforcement::class)),
            $checker,
            $router,
        );

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRedirectsAdminUserWhenEnforced(): void
    {
        $checker = $this->createStub(TwoFactorEnforcementCheckerInterface::class);
        $checker->method('shouldEnforceForAdminUser')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->with('three_brs_admin_two_factor_setup')
            ->willReturn('/admin/two-factor/setup');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestAdminUserForEnforcement::class)),
            $checker,
            $router,
        );

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame('/admin/two-factor/setup', $event->getResponse()->getTargetUrl());
    }

    public function testDoesNotRedirectAdminUserWhenNotEnforced(): void
    {
        $checker = $this->createStub(TwoFactorEnforcementCheckerInterface::class);
        $checker->method('shouldEnforceForAdminUser')->willReturn(false);

        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::never())->method('generate');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestAdminUserForEnforcement::class)),
            $checker,
            $router,
        );

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingOnApiRequestEvenWhenEnforcementApplies(): void
    {
        // 'api_entrypoint' does not start with '_', so the route-name exclusion
        // would let it through — only the path-based API guard keeps the API
        // untouched, as if the plugin were not installed.
        $checker = $this->createMock(TwoFactorEnforcementCheckerInterface::class);
        $checker->expects(self::never())->method('shouldEnforceForShopUser');
        $checker->expects(self::never())->method('shouldEnforceForAdminUser');

        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::never())->method('generate');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUserForEnforcement::class)),
            $checker,
            $router,
        );

        $event = $this->createEvent('api_entrypoint', '/api/v2/admin/orders');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }
}
