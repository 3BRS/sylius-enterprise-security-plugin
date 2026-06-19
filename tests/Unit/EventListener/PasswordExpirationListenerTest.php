<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\PasswordExpirationListener;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationCheckerInterface;

/** @internal test double: shop user with expiration support */
interface TestShopUser extends PasswordExpirationShopUserInterface, UserInterface
{
}

/** @internal test double: admin user with expiration support */
interface TestAdminUser extends PasswordExpirationAdminUserInterface, UserInterface
{
}

/** @internal test double: social login user — has expiration interface but no password */
interface TestShopUserWithoutPassword extends PasswordExpirationShopUserInterface, PasswordAuthenticatedUserInterface, UserInterface
{
}

#[CoversClass(PasswordExpirationListener::class)]
class PasswordExpirationListenerTest extends TestCase
{
    private function createListener(
        TokenStorageInterface $tokenStorage,
        PasswordExpirationCheckerInterface $checker,
        RouterInterface $router,
        string $apiRoute = '/api/v2',
    ): PasswordExpirationListener {
        return new PasswordExpirationListener($tokenStorage, $checker, $router, $apiRoute);
    }

    /**
     * @param array<string, string> $headers
     */
    private function createEvent(string $route = 'some_route', array $headers = [], string $path = '/'): RequestEvent
    {
        $request = Request::create($path);
        $request->attributes->set('_route', $route);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

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
        $checker = $this->createMock(PasswordExpirationCheckerInterface::class);
        $checker->expects(self::never())->method('isShopUserPasswordExpired');

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
        $checker = $this->createMock(PasswordExpirationCheckerInterface::class);
        $checker->expects(self::never())->method('isShopUserPasswordExpired');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUser::class)),
            $checker,
            $this->createStub(RouterInterface::class),
        );

        $event = $this->createEvent('sylius_shop_login');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingWhenUserHasNoPassword(): void
    {
        $user = $this->createStub(TestShopUserWithoutPassword::class);
        $user->method('getPassword')->willReturn(null);

        $checker = $this->createMock(PasswordExpirationCheckerInterface::class);
        $checker->expects(self::never())->method('isShopUserPasswordExpired');
        $checker->expects(self::never())->method('isAdminUserPasswordExpired');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($user),
            $checker,
            $this->createStub(RouterInterface::class),
        );

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRedirectsShopUserWithExpiredPassword(): void
    {
        $checker = $this->createStub(PasswordExpirationCheckerInterface::class);
        $checker->method('isShopUserPasswordExpired')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->with('sylius_shop_account_change_password')
            ->willReturn('/en_US/account/change-password');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUser::class)),
            $checker,
            $router,
        );

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame('/en_US/account/change-password', $event->getResponse()->getTargetUrl());
    }

    public function testDoesNotRedirectShopUserWithValidPassword(): void
    {
        $checker = $this->createStub(PasswordExpirationCheckerInterface::class);
        $checker->method('isShopUserPasswordExpired')->willReturn(false);

        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::never())->method('generate');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUser::class)),
            $checker,
            $router,
        );

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRedirectsAdminUserWithExpiredPassword(): void
    {
        $checker = $this->createStub(PasswordExpirationCheckerInterface::class);
        $checker->method('isAdminUserPasswordExpired')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->with('three_brs_admin_force_password_change')
            ->willReturn('/admin/force-password-change');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestAdminUser::class)),
            $checker,
            $router,
        );

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame('/admin/force-password-change', $event->getResponse()->getTargetUrl());
    }

    public function testDoesNotRedirectAdminUserWithValidPassword(): void
    {
        $checker = $this->createStub(PasswordExpirationCheckerInterface::class);
        $checker->method('isAdminUserPasswordExpired')->willReturn(false);

        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::never())->method('generate');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestAdminUser::class)),
            $checker,
            $router,
        );

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNotRedirectLiveComponentRequest(): void
    {
        $checker = $this->createMock(PasswordExpirationCheckerInterface::class);
        $checker->expects(self::never())->method('isShopUserPasswordExpired');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUser::class)),
            $checker,
            $this->createStub(RouterInterface::class),
        );

        $event = $this->createEvent(headers: ['Accept' => 'application/vnd.live-component+html']);
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNotRedirectXmlHttpRequest(): void
    {
        $checker = $this->createMock(PasswordExpirationCheckerInterface::class);
        $checker->expects(self::never())->method('isShopUserPasswordExpired');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUser::class)),
            $checker,
            $this->createStub(RouterInterface::class),
        );

        $event = $this->createEvent(headers: ['X-Requested-With' => 'XMLHttpRequest']);
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingOnApiRequestEvenWhenPasswordExpired(): void
    {
        // 'api_entrypoint' does not start with '_', so only the path-based API
        // guard keeps the API untouched — the plugin must never redirect an API
        // request to the web change-password page.
        $checker = $this->createMock(PasswordExpirationCheckerInterface::class);
        $checker->expects(self::never())->method('isShopUserPasswordExpired');
        $checker->expects(self::never())->method('isAdminUserPasswordExpired');

        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::never())->method('generate');

        $listener = $this->createListener(
            $this->tokenStorageWithUser($this->createStub(TestShopUser::class)),
            $checker,
            $router,
        );

        $event = $this->createEvent('api_entrypoint', [], '/api/v2/admin/orders');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }
}
