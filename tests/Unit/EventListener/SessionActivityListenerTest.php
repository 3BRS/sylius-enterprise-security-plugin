<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\SessionActivityListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionTrackerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;

#[CoversClass(SessionActivityListener::class)]
class SessionActivityListenerTest extends TestCase
{
    public function testCallsCustomerTrackerForShopUser(): void
    {
        $customerTracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $customerTracker->expects(self::once())->method('touch')->with('the-session');

        $adminTracker = $this->createMock(AdminUserSessionTrackerInterface::class);
        $adminTracker->expects(self::never())->method('touch');

        $listener = $this->makeListener(
            $customerTracker,
            $adminTracker,
            $this->createStub(ShopUserInterface::class),
            true,
            true,
        );
        $listener->onKernelRequest($this->makeRequestEvent('the-session'));
    }

    public function testCallsAdminTrackerForAdminUser(): void
    {
        $customerTracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $customerTracker->expects(self::never())->method('touch');

        $adminTracker = $this->createMock(AdminUserSessionTrackerInterface::class);
        $adminTracker->expects(self::once())->method('touch')->with('admin-session');

        $listener = $this->makeListener(
            $customerTracker,
            $adminTracker,
            $this->createStub(AdminUserInterface::class),
            true,
            true,
        );
        $listener->onKernelRequest($this->makeRequestEvent('admin-session'));
    }

    public function testSkipsWhenBothFlagsAreOff(): void
    {
        $customerTracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $customerTracker->expects(self::never())->method('touch');

        $listener = $this->makeListener(
            $customerTracker,
            $this->createStub(AdminUserSessionTrackerInterface::class),
            $this->createStub(ShopUserInterface::class),
            false,
            false,
        );
        $listener->onKernelRequest($this->makeRequestEvent('whatever'));
    }

    public function testSkipsForSubRequests(): void
    {
        $customerTracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $customerTracker->expects(self::never())->method('touch');

        $listener = $this->makeListener(
            $customerTracker,
            $this->createStub(AdminUserSessionTrackerInterface::class),
            $this->createStub(ShopUserInterface::class),
            true,
            true,
        );
        $listener->onKernelRequest($this->makeRequestEvent('whatever', isMain: false));
    }

    protected function makeListener(
        CustomerSessionTrackerInterface $customerTracker,
        AdminUserSessionTrackerInterface $adminTracker,
        object $user,
        bool $customerEnabled,
        bool $adminEnabled,
    ): SessionActivityListener {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        return new SessionActivityListener(
            $tokenStorage,
            $customerTracker,
            $adminTracker,
            $customerEnabled,
            $adminEnabled,
        );
    }

    protected function makeRequestEvent(string $sessionId, bool $isMain = true): RequestEvent
    {
        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn($sessionId);

        $request = $this->createStub(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);

        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent(
            $kernel,
            $request,
            $isMain ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }
}
