<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\Http\Authentication\AuthenticationRequiredHandlerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\PasskeyLoginVerifyController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\AdminPasskeyAssertionVerifierInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\AdminPasskeyAssertionResult;

#[CoversClass(PasskeyLoginVerifyController::class)]
class PasskeyLoginVerifyControllerTest extends TestCase
{
    public function testReturns404WhenDisabled(): void
    {
        $controller = $this->createController(
            verifier: $this->createStub(AdminPasskeyAssertionVerifierInterface::class),
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            eventDispatcher: $this->createStub(EventDispatcherInterface::class),
            twoFactorHandler: $this->createStub(AuthenticationRequiredHandlerInterface::class),
            router: $this->createStub(RouterInterface::class),
            enabled: false,
            skipTwoFactorWhenUserVerified: false,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller($this->buildRequest());
    }

    public function testSkipsTwoFactorDispatchWhenUserVerifiedAndFlagEnabled(): void
    {
        $adminUser = $this->buildAdminUser();

        $verifier = $this->createStub(AdminPasskeyAssertionVerifierInterface::class);
        $verifier->method('verify')->willReturn(new AdminPasskeyAssertionResult($adminUser, true));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())
            ->method('setToken')
            ->with(self::isInstanceOf(PostAuthenticationToken::class));

        $twoFactorHandler = $this->createMock(AuthenticationRequiredHandlerInterface::class);
        $twoFactorHandler->expects(self::never())->method('onAuthenticationRequired');

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/admin/dashboard');

        $controller = $this->createController(
            verifier: $verifier,
            tokenStorage: $tokenStorage,
            eventDispatcher: $eventDispatcher,
            twoFactorHandler: $twoFactorHandler,
            router: $router,
            enabled: true,
            skipTwoFactorWhenUserVerified: true,
        );

        $response = $controller($this->buildRequest());

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        $payload = (array) json_decode((string) $response->getContent(), true);
        self::assertTrue($payload['ok']);
        self::assertSame('/admin/dashboard', $payload['redirect']);
    }

    public function testDispatchesAuthEventWhenUserNotVerified(): void
    {
        $adminUser = $this->buildAdminUser();

        $verifier = $this->createStub(AdminPasskeyAssertionVerifierInterface::class);
        $verifier->method('verify')->willReturn(new AdminPasskeyAssertionResult($adminUser, false));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(AuthenticationTokenCreatedEvent::class))
            ->willReturnArgument(0);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())->method('setToken');

        $twoFactorHandler = $this->createMock(AuthenticationRequiredHandlerInterface::class);
        $twoFactorHandler->expects(self::never())->method('onAuthenticationRequired');

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/admin/dashboard');

        $controller = $this->createController(
            verifier: $verifier,
            tokenStorage: $tokenStorage,
            eventDispatcher: $eventDispatcher,
            twoFactorHandler: $twoFactorHandler,
            router: $router,
            enabled: true,
            skipTwoFactorWhenUserVerified: true,
        );

        $response = $controller($this->buildRequest());

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testDispatchesAuthEventWhenSkipFlagDisabledEvenIfUserVerified(): void
    {
        $adminUser = $this->buildAdminUser();

        $verifier = $this->createStub(AdminPasskeyAssertionVerifierInterface::class);
        $verifier->method('verify')->willReturn(new AdminPasskeyAssertionResult($adminUser, true));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->willReturnArgument(0);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())->method('setToken');

        $twoFactorHandler = $this->createMock(AuthenticationRequiredHandlerInterface::class);
        $twoFactorHandler->expects(self::never())->method('onAuthenticationRequired');

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/admin/dashboard');

        $controller = $this->createController(
            verifier: $verifier,
            tokenStorage: $tokenStorage,
            eventDispatcher: $eventDispatcher,
            twoFactorHandler: $twoFactorHandler,
            router: $router,
            enabled: true,
            skipTwoFactorWhenUserVerified: false,
        );

        $controller($this->buildRequest());
    }

    public function testRedirectsToTwoFactorWhenListenerWrapsTokenInTwoFactorToken(): void
    {
        $adminUser = $this->buildAdminUser();

        $verifier = $this->createStub(AdminPasskeyAssertionVerifierInterface::class);
        $verifier->method('verify')->willReturn(new AdminPasskeyAssertionResult($adminUser, false));

        $twoFactorToken = $this->createStub(TwoFactorTokenInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (AuthenticationTokenCreatedEvent $event) use ($twoFactorToken): AuthenticationTokenCreatedEvent {
                $event->setAuthenticatedToken($twoFactorToken);

                return $event;
            });

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())
            ->method('setToken')
            ->with($twoFactorToken);

        $twoFactorHandler = $this->createMock(AuthenticationRequiredHandlerInterface::class);
        $twoFactorHandler->expects(self::once())
            ->method('onAuthenticationRequired')
            ->willReturn(new RedirectResponse('/admin/2fa'));

        $controller = $this->createController(
            verifier: $verifier,
            tokenStorage: $tokenStorage,
            eventDispatcher: $eventDispatcher,
            twoFactorHandler: $twoFactorHandler,
            router: $this->createStub(RouterInterface::class),
            enabled: true,
            skipTwoFactorWhenUserVerified: false,
        );

        $response = $controller($this->buildRequest());

        self::assertInstanceOf(JsonResponse::class, $response);
        $payload = (array) json_decode((string) $response->getContent(), true);
        self::assertTrue($payload['ok']);
        self::assertSame('/admin/2fa', $payload['redirect']);
    }

    public function testReturnsBadRequestWhenCredentialPayloadMissing(): void
    {
        $controller = $this->createController(
            verifier: $this->createStub(AdminPasskeyAssertionVerifierInterface::class),
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            eventDispatcher: $this->createStub(EventDispatcherInterface::class),
            twoFactorHandler: $this->createStub(AuthenticationRequiredHandlerInterface::class),
            router: $this->createStub(RouterInterface::class),
            enabled: true,
            skipTwoFactorWhenUserVerified: false,
        );

        $request = Request::create('/admin/passkey/login/verify', 'POST', content: '{}');
        $response = $controller($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    protected function buildAdminUser(): AdminUserInterface
    {
        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getUserIdentifier')->willReturn('admin@example.com');
        $user->method('getRoles')->willReturn(['ROLE_ADMINISTRATION_ACCESS']);

        return $user;
    }

    protected function buildRequest(): Request
    {
        return Request::create(
            '/admin/passkey/login/verify',
            'POST',
            content: '{"credential":"{}"}',
        );
    }

    protected function createController(
        AdminPasskeyAssertionVerifierInterface $verifier,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher,
        AuthenticationRequiredHandlerInterface $twoFactorHandler,
        RouterInterface $router,
        bool $enabled,
        bool $skipTwoFactorWhenUserVerified,
    ): PasskeyLoginVerifyController {
        return new PasskeyLoginVerifyController(
            verifier: $verifier,
            tokenStorage: $tokenStorage,
            eventDispatcher: $eventDispatcher,
            twoFactorHandler: $twoFactorHandler,
            router: $router,
            logger: $this->createStub(LoggerInterface::class),
            enabled: $enabled,
            skipTwoFactorWhenUserVerified: $skipTwoFactorWhenUserVerified,
        );
    }
}
