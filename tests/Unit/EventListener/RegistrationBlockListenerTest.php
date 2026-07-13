<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\RegistrationBlockListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

#[CoversClass(RegistrationBlockListener::class)]
class RegistrationBlockListenerTest extends TestCase
{
    public function testBlocksRegisterPostWhenPasswordLoginDisabled(): void
    {
        $event = $this->event('sylius_shop_register', 'POST');

        $this->listener(passwordLoginEnabled: false)->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
    }

    public function testAllowsRegisterPostWhenPasswordLoginEnabled(): void
    {
        $event = $this->event('sylius_shop_register', 'POST');

        $this->listener(passwordLoginEnabled: true)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testIgnoresRegisterGet(): void
    {
        // GET still renders the page (with OAuth buttons) — only the actual submission is blocked.
        $event = $this->event('sylius_shop_register', 'GET');

        $this->listener(passwordLoginEnabled: false)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testIgnoresOtherRoutes(): void
    {
        $event = $this->event('sylius_shop_login', 'POST');

        $this->listener(passwordLoginEnabled: false)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    protected function listener(bool $passwordLoginEnabled): RegistrationBlockListener
    {
        $checker = $this->createStub(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')->willReturn($passwordLoginEnabled);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/login');

        return new RegistrationBlockListener($checker, $router);
    }

    protected function event(string $route, string $method): RequestEvent
    {
        $request = Request::create('/register', $method);
        $request->attributes->set('_route', $route);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
