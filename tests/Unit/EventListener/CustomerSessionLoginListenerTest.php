<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\CustomerSessionLoginListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionLoginHandlerInterface;

#[CoversClass(CustomerSessionLoginListener::class)]
class CustomerSessionLoginListenerTest extends TestCase
{
    public function testDelegatesToHandlerForShopUser(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $request = $this->createStub(Request::class);

        $handler = $this->createMock(CustomerSessionLoginHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($user, $request);

        $listener = new CustomerSessionLoginListener($handler);
        $listener->onLoginSuccess($this->makeEvent($user, $request));
    }

    public function testSkipsForNonShopUser(): void
    {
        $handler = $this->createMock(CustomerSessionLoginHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $listener = new CustomerSessionLoginListener($handler);
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
