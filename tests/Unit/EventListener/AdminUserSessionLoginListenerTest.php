<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\AdminUserSessionLoginListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionLoginHandlerInterface;

#[CoversClass(AdminUserSessionLoginListener::class)]
class AdminUserSessionLoginListenerTest extends TestCase
{
    public function testDelegatesToHandlerForAdminUser(): void
    {
        $user = $this->createStub(AdminUserInterface::class);
        $request = $this->createStub(Request::class);

        $handler = $this->createMock(AdminUserSessionLoginHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($user, $request);

        $listener = new AdminUserSessionLoginListener($handler);
        $listener->onLoginSuccess($this->makeEvent($user, $request));
    }

    public function testSkipsForNonAdminUser(): void
    {
        $handler = $this->createMock(AdminUserSessionLoginHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $listener = new AdminUserSessionLoginListener($handler);
        $listener->onLoginSuccess($this->makeEvent($this->createStub(UserInterface::class), $this->createStub(Request::class)));
    }

    protected function makeEvent(object $user, Request $request): LoginSuccessEvent
    {
        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn($request);

        return $event;
    }
}
