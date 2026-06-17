<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\PasskeyLoginVerifyController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\AdminPasskeyAssertionVerifierInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\AdminPasskeyAssertionResult;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionLoginHandlerInterface;

#[CoversClass(PasskeyLoginVerifyController::class)]
class PasskeyLoginVerifyControllerTest extends TestCase
{
    public function testReturns404WhenDisabled(): void
    {
        $controller = $this->createController(
            verifier: $this->createStub(AdminPasskeyAssertionVerifierInterface::class),
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            router: $this->createStub(RouterInterface::class),
            enabled: false,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller($this->buildRequest());
    }

    public function testAuthenticatesDirectlyAndBypassesTwoFactor(): void
    {
        $adminUser = $this->buildAdminUser();

        $verifier = $this->createStub(AdminPasskeyAssertionVerifierInterface::class);
        $verifier->method('verify')->willReturn(new AdminPasskeyAssertionResult($adminUser));

        // Passkey writes the token directly — no scheb event dispatch, no 2FA challenge.
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())
            ->method('setToken')
            ->with(self::isInstanceOf(PostAuthenticationToken::class));

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/admin/dashboard');

        $controller = $this->createController(
            verifier: $verifier,
            tokenStorage: $tokenStorage,
            router: $router,
            enabled: true,
        );

        $response = $controller($this->buildRequest());

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        $payload = (array) json_decode((string) $response->getContent(), true);
        self::assertTrue($payload['ok']);
        self::assertSame('/admin/dashboard', $payload['redirect']);
    }

    public function testReturnsBadRequestWhenCredentialPayloadMissing(): void
    {
        $controller = $this->createController(
            verifier: $this->createStub(AdminPasskeyAssertionVerifierInterface::class),
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            router: $this->createStub(RouterInterface::class),
            enabled: true,
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
        RouterInterface $router,
        bool $enabled,
    ): PasskeyLoginVerifyController {
        return new PasskeyLoginVerifyController(
            verifier: $verifier,
            tokenStorage: $tokenStorage,
            router: $router,
            logger: $this->createStub(LoggerInterface::class),
            sessionLoginHandler: $this->createStub(AdminUserSessionLoginHandlerInterface::class),
            enabled: $enabled,
        );
    }
}
