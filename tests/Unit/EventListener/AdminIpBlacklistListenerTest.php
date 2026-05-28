<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\AdminIpBlacklistListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpBlacklist\IpBlacklistCheckerInterface;

#[CoversClass(AdminIpBlacklistListener::class)]
class AdminIpBlacklistListenerTest extends TestCase
{
    private function createEvent(string $path = '/admin/dashboard', ?string $remoteAddr = '10.0.0.5'): RequestEvent
    {
        $request = Request::create($path, 'GET', server: $remoteAddr === null ? [] : ['REMOTE_ADDR' => $remoteAddr]);

        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    public function testPassesWhenFeatureDisabled(): void
    {
        $checker = $this->createMock(IpBlacklistCheckerInterface::class);
        $checker->method('isFeatureEnabled')->willReturn(false);
        $checker->expects(self::never())->method('isBlockedByGlobal');

        $listener = new AdminIpBlacklistListener($checker);

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testPassesForNonAdminPath(): void
    {
        $checker = $this->createMock(IpBlacklistCheckerInterface::class);
        $checker->method('isFeatureEnabled')->willReturn(true);
        $checker->expects(self::never())->method('isBlockedByGlobal');

        $listener = new AdminIpBlacklistListener($checker);

        $event = $this->createEvent('/shop/checkout');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAllowsIpNotBlocked(): void
    {
        $checker = $this->createStub(IpBlacklistCheckerInterface::class);
        $checker->method('isFeatureEnabled')->willReturn(true);
        $checker->method('isBlockedByGlobal')->willReturn(false);

        $listener = new AdminIpBlacklistListener($checker);

        $event = $this->createEvent('/admin/login');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDeniesIpInBlacklist(): void
    {
        $checker = $this->createStub(IpBlacklistCheckerInterface::class);
        $checker->method('isFeatureEnabled')->willReturn(true);
        $checker->method('isBlockedByGlobal')->willReturn(true);

        $listener = new AdminIpBlacklistListener($checker);

        $event = $this->createEvent('/admin/dashboard');
        $listener->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
    }

    public function testIgnoresSubRequests(): void
    {
        $checker = $this->createMock(IpBlacklistCheckerInterface::class);
        $checker->expects(self::never())->method('isFeatureEnabled');

        $listener = new AdminIpBlacklistListener($checker);

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
