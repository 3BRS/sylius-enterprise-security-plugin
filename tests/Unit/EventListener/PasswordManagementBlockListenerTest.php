<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\PasswordManagementBlockListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

#[CoversClass(PasswordManagementBlockListener::class)]
class PasswordManagementBlockListenerTest extends TestCase
{
    /** @return iterable<string, array{string, SettingsScope, string}> */
    public static function blockedRoutes(): iterable
    {
        yield 'shop forgotten password' => ['sylius_shop_request_password_reset_token', SettingsScope::CUSTOMER, 'sylius_shop_login'];
        yield 'shop password reset' => ['sylius_shop_password_reset', SettingsScope::CUSTOMER, 'sylius_shop_login'];
        yield 'admin forgotten password page' => ['sylius_admin_render_reset_password_page', SettingsScope::ADMIN, 'sylius_admin_login'];
        yield 'admin forgotten password request' => ['sylius_admin_request_password_reset', SettingsScope::ADMIN, 'sylius_admin_login'];
        yield 'admin password reset page' => ['sylius_admin_render_password_reset', SettingsScope::ADMIN, 'sylius_admin_login'];
        yield 'admin password reset' => ['sylius_admin_password_reset', SettingsScope::ADMIN, 'sylius_admin_login'];
    }

    #[DataProvider('blockedRoutes')]
    public function testRedirectsWhenPasswordLoginDisabledForTheScope(string $route, SettingsScope $scope, string $redirectRoute): void
    {
        $event = $this->event($route);

        $this->listener(disabledScope: $scope)->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/' . $redirectRoute, $response->getTargetUrl());
        self::assertSame(
            ['three_brs.password_login.password_management_disabled'],
            $event->getRequest()->getSession()->getFlashBag()->peek('error'),
        );
    }

    #[DataProvider('blockedRoutes')]
    public function testLeavesTheRouteAloneWhenPasswordLoginEnabled(string $route, SettingsScope $scope, string $redirectRoute): void
    {
        $event = $this->event($route);

        $this->listener(disabledScope: null)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNotBlockTheOtherScope(): void
    {
        // Admin password login off must not close the customer's forgotten-password page.
        $event = $this->event('sylius_shop_request_password_reset_token');

        $this->listener(disabledScope: SettingsScope::ADMIN)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testLeavesTheChangePasswordPageOpen(): void
    {
        // It demands the current password, and a password changed there could not be signed in with
        // anyway — so there is nothing to close.
        $event = $this->event('sylius_shop_account_change_password');

        $this->listener(disabledScope: SettingsScope::CUSTOMER)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testIgnoresUnrelatedRoutes(): void
    {
        $event = $this->event('sylius_shop_homepage');

        $this->listener(disabledScope: SettingsScope::CUSTOMER)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testIgnoresSubRequests(): void
    {
        // A blocked page embedded as a fragment must not redirect the whole response.
        $event = $this->event('sylius_shop_request_password_reset_token', HttpKernelInterface::SUB_REQUEST);

        $this->listener(disabledScope: SettingsScope::CUSTOMER)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    protected function listener(?SettingsScope $disabledScope): PasswordManagementBlockListener
    {
        $checker = $this->createStub(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')->willReturnCallback(
            static fn (SettingsScope $scope): bool => $scope !== $disabledScope,
        );

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => '/' . $route);

        return new PasswordManagementBlockListener($checker, $router);
    }

    protected function event(string $route, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/whatever');
        $request->attributes->set('_route', $route);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $requestType);
    }
}
