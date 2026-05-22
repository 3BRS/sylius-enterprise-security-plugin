<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\EnterpriseSecurityBundle\Unit\IpRestriction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpRestriction\AbstractIpRestrictionListener;

#[CoversClass(AbstractIpRestrictionListener::class)]
class AbstractIpRestrictionListenerTest extends TestCase
{
    public function testIgnoresWhenFeatureDisabled(): void
    {
        $listener = $this->createListener(false, false, $this->tokenStorageEmpty());

        $event = $this->createEvent();
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testIgnoresNonAdminPath(): void
    {
        $listener = $this->createListener(true, false, $this->tokenStorageEmpty());

        $event = $this->createEvent('/shop/checkout');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testIgnoresPathPrefixMatch(): void
    {
        $listener = $this->createListener(true, false, $this->tokenStorageEmpty());

        // `/admin-other` should NOT be matched by `/admin` prefix; only `/admin` or `/admin/...`.
        $event = $this->createEvent('/admin-other/x');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testPassesWhenRequestAllowed(): void
    {
        $listener = $this->createListener(true, true, $this->tokenStorageEmpty());

        $event = $this->createEvent('/admin/login');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDenies403WhenRequestNotAllowed(): void
    {
        $listener = $this->createListener(true, false, $this->tokenStorageEmpty());

        $event = $this->createEvent('/admin/login');
        $listener->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        self::assertSame('Access denied', $response->getContent());
    }

    public function testPassesAuthenticatedUserWhenAllowed(): void
    {
        $user = $this->createStub(UserInterface::class);
        $listener = $this->createListener(true, true, $this->tokenStorageWithUser($user));

        $event = $this->createEvent('/admin/dashboard');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDeniesAuthenticatedUserWhenNotAllowed(): void
    {
        $user = $this->createStub(UserInterface::class);
        $listener = $this->createListener(true, false, $this->tokenStorageWithUser($user));

        $event = $this->createEvent('/admin/dashboard');
        $listener->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testIgnoresSubRequests(): void
    {
        $listener = $this->createListener(true, false, $this->tokenStorageEmpty());

        $request = Request::create('/admin/dashboard');
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
        );

        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    private function createListener(
        bool $featureEnabled,
        bool $requestAllowed,
        TokenStorageInterface $tokenStorage,
    ): AbstractIpRestrictionListener {
        return new class($featureEnabled, $requestAllowed, $tokenStorage) extends AbstractIpRestrictionListener {
            public function __construct(
                protected bool $featureEnabled,
                protected bool $requestAllowed,
                TokenStorageInterface $tokenStorage,
            ) {
                parent::__construct($tokenStorage);
            }

            protected function isFeatureEnabled(): bool
            {
                return $this->featureEnabled;
            }

            protected function isRequestAllowed(?UserInterface $user, string $ip): bool
            {
                return $this->requestAllowed;
            }
        };
    }

    private function createEvent(string $path = '/admin/dashboard'): RequestEvent
    {
        $request = Request::create($path, 'GET', server: [
            'REMOTE_ADDR' => '10.0.0.5',
        ]);

        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function tokenStorageEmpty(): TokenStorageInterface
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        return $tokenStorage;
    }

    private function tokenStorageWithUser(UserInterface $user): TokenStorageInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        return $tokenStorage;
    }
}
