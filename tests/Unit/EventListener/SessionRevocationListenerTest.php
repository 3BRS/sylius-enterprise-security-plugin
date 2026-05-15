<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\SessionRevocationListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;

#[CoversClass(SessionRevocationListener::class)]
class SessionRevocationListenerTest extends TestCase
{
    public function testRedirectsShopUserWhenSessionIsRevoked(): void
    {
        $session = new CustomerSession();
        $session->setSessionId('sess-1');
        $session->setRevokedAt(new \DateTimeImmutable());

        $customerRepository = $this->createStub(CustomerSessionRepositoryInterface::class);
        $customerRepository->method('findOneBySessionId')->willReturn($session);

        $event = $this->makeEvent('sess-1', $this->createStub(ShopUserInterface::class), invalidatesSession: true);

        $listener = new SessionRevocationListener(
            $event->getRequest()->attributes->get('_test_token_storage'),
            $customerRepository,
            $this->createStub(AdminUserSessionRepositoryInterface::class),
            $this->makeRouter('/login'),
            true,
            true,
        );
        $listener->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
    }

    public function testDoesNotRedirectActiveShopSession(): void
    {
        $session = new CustomerSession();
        $session->setSessionId('sess-1');

        $customerRepository = $this->createStub(CustomerSessionRepositoryInterface::class);
        $customerRepository->method('findOneBySessionId')->willReturn($session);

        $event = $this->makeEvent('sess-1', $this->createStub(ShopUserInterface::class));

        $listener = new SessionRevocationListener(
            $event->getRequest()->attributes->get('_test_token_storage'),
            $customerRepository,
            $this->createStub(AdminUserSessionRepositoryInterface::class),
            $this->makeRouter('/login'),
            true,
            true,
        );
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRedirectsAdminUserWhenSessionIsRevoked(): void
    {
        $session = new AdminUserSession();
        $session->setSessionId('admin-1');
        $session->setRevokedAt(new \DateTimeImmutable());

        $adminRepository = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $adminRepository->method('findOneBySessionId')->willReturn($session);

        $event = $this->makeEvent('admin-1', $this->createStub(AdminUserInterface::class), invalidatesSession: true);

        $listener = new SessionRevocationListener(
            $event->getRequest()->attributes->get('_test_token_storage'),
            $this->createStub(CustomerSessionRepositoryInterface::class),
            $adminRepository,
            $this->makeRouter('/admin/login'),
            true,
            true,
        );
        $listener->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/login', $response->getTargetUrl());
    }

    public function testDoesNotRedirectActiveAdminSession(): void
    {
        $session = new AdminUserSession();
        $session->setSessionId('admin-1');

        $adminRepository = $this->createStub(AdminUserSessionRepositoryInterface::class);
        $adminRepository->method('findOneBySessionId')->willReturn($session);

        $event = $this->makeEvent('admin-1', $this->createStub(AdminUserInterface::class));

        $listener = new SessionRevocationListener(
            $event->getRequest()->attributes->get('_test_token_storage'),
            $this->createStub(CustomerSessionRepositoryInterface::class),
            $adminRepository,
            $this->makeRouter('/admin/login'),
            true,
            true,
        );
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSkipsWhenBothFlagsAreOff(): void
    {
        $event = $this->makeEvent('sess-1', $this->createStub(ShopUserInterface::class));

        $listener = new SessionRevocationListener(
            $event->getRequest()->attributes->get('_test_token_storage'),
            $this->createStub(CustomerSessionRepositoryInterface::class),
            $this->createStub(AdminUserSessionRepositoryInterface::class),
            $this->makeRouter('/login'),
            false,
            false,
        );
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    protected function makeRouter(string $url): RouterInterface
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn($url);

        return $router;
    }

    protected function makeEvent(string $sessionId, object $user, bool $invalidatesSession = false): RequestEvent
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')->willReturn($sessionId);
        if ($invalidatesSession) {
            $session->expects(self::once())->method('invalidate');
        } else {
            $session->expects(self::never())->method('invalidate');
        }

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);
        if ($invalidatesSession) {
            $tokenStorage->expects(self::once())->method('setToken')->with(null);
        } else {
            $tokenStorage->expects(self::never())->method('setToken');
        }

        $request = new Request();
        $request->setSession($session);
        $request->attributes->set('_test_token_storage', $tokenStorage);

        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
