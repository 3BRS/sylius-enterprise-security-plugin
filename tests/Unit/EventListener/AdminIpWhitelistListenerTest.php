<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\AdminIpWhitelistListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpWhitelist\IpWhitelistCheckerInterface;

#[CoversClass(AdminIpWhitelistListener::class)]
class AdminIpWhitelistListenerTest extends TestCase
{
    private function createListener(
        IpWhitelistCheckerInterface $checker,
        TokenStorageInterface $tokenStorage,
    ): AdminIpWhitelistListener {
        return new AdminIpWhitelistListener($checker, $tokenStorage);
    }

    private function createEvent(string $path = '/admin/dashboard', ?string $remoteAddr = '10.0.0.5'): RequestEvent
    {
        $request = Request::create($path, 'GET', server: $remoteAddr === null ? [] : ['REMOTE_ADDR' => $remoteAddr]);

        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function tokenStorageWithAdmin(AdminUserInterface $admin): TokenStorageInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($admin);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        return $tokenStorage;
    }

    private function tokenStorageEmpty(): TokenStorageInterface
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        return $tokenStorage;
    }

    public function testPassesWhenFeatureDisabled(): void
    {
        $checker = $this->createMock(IpWhitelistCheckerInterface::class);
        $checker->method('isFeatureEnabled')->willReturn(false);
        $checker->expects(self::never())->method('isAllowedByGlobal');

        $listener = $this->createListener($checker, $this->tokenStorageEmpty());

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testPassesForNonAdminPath(): void
    {
        $checker = $this->createMock(IpWhitelistCheckerInterface::class);
        $checker->method('isFeatureEnabled')->willReturn(true);
        $checker->expects(self::never())->method('isAllowedByGlobal');

        $listener = $this->createListener($checker, $this->tokenStorageEmpty());

        $event = $this->createEvent('/shop/checkout');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAllowsAnonymousIpInGlobal(): void
    {
        $checker = $this->createStub(IpWhitelistCheckerInterface::class);
        $checker->method('isFeatureEnabled')->willReturn(true);
        $checker->method('isAllowedByGlobal')->willReturn(true);

        $listener = $this->createListener($checker, $this->tokenStorageEmpty());

        $event = $this->createEvent('/admin/login');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDeniesAnonymousIpNotInGlobal(): void
    {
        $checker = $this->createStub(IpWhitelistCheckerInterface::class);
        $checker->method('isFeatureEnabled')->willReturn(true);
        $checker->method('isAllowedByGlobal')->willReturn(false);

        $listener = $this->createListener($checker, $this->tokenStorageEmpty());

        $event = $this->createEvent('/admin/login');
        $listener->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
    }

    public function testAllowsAuthenticatedAdminWithMatchingIp(): void
    {
        $admin = $this->createStub(AdminUserInterface::class);

        $checker = $this->createStub(IpWhitelistCheckerInterface::class);
        $checker->method('isFeatureEnabled')->willReturn(true);
        $checker->method('isAllowedForAdmin')->willReturn(true);

        $listener = $this->createListener($checker, $this->tokenStorageWithAdmin($admin));

        $event = $this->createEvent('/admin/dashboard');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDeniesAuthenticatedAdminWithNonMatchingIp(): void
    {
        $admin = $this->createStub(AdminUserInterface::class);

        $checker = $this->createStub(IpWhitelistCheckerInterface::class);
        $checker->method('isFeatureEnabled')->willReturn(true);
        $checker->method('isAllowedForAdmin')->willReturn(false);

        $listener = $this->createListener($checker, $this->tokenStorageWithAdmin($admin));

        $event = $this->createEvent('/admin/dashboard');
        $listener->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testIgnoresSubRequests(): void
    {
        $checker = $this->createMock(IpWhitelistCheckerInterface::class);
        $checker->expects(self::never())->method('isFeatureEnabled');

        $listener = $this->createListener($checker, $this->tokenStorageEmpty());

        $request = Request::create('/admin/dashboard');
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
        );

        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }
}
