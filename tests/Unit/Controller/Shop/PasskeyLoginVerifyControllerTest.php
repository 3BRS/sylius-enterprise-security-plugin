<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Shop;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop\PasskeyLoginVerifyController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyAssertionVerifierInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyAssertionResult;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionLoginHandlerInterface;

#[CoversClass(PasskeyLoginVerifyController::class)]
class PasskeyLoginVerifyControllerTest extends TestCase
{
    public function testReturns404WhenDisabled(): void
    {
        $controller = $this->createController(
            verifier: $this->createStub(CustomerPasskeyAssertionVerifierInterface::class),
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            router: $this->createStub(RouterInterface::class),
            enabled: false,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller($this->buildRequest());
    }

    public function testAuthenticatesDirectlyAndBypassesTwoFactor(): void
    {
        $shopUser = $this->buildShopUser();

        $verifier = $this->createStub(CustomerPasskeyAssertionVerifierInterface::class);
        $verifier->method('verify')->willReturn(new CustomerPasskeyAssertionResult($shopUser));

        // Passkey writes the token directly — no scheb event dispatch, no 2FA challenge.
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())
            ->method('setToken')
            ->with(self::isInstanceOf(PostAuthenticationToken::class));

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/account/dashboard');

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
        self::assertSame('/account/dashboard', $payload['redirect']);
    }

    public function testReturnsBadRequestWhenCredentialPayloadMissing(): void
    {
        $controller = $this->createController(
            verifier: $this->createStub(CustomerPasskeyAssertionVerifierInterface::class),
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            router: $this->createStub(RouterInterface::class),
            enabled: true,
        );

        $request = Request::create('/passkey/login/verify', 'POST', content: '{}');
        $response = $controller($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    protected function buildShopUser(): ShopUserInterface
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user@example.com');
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        return $user;
    }

    protected function buildRequest(): Request
    {
        return Request::create(
            '/passkey/login/verify',
            'POST',
            content: '{"credential":"{}"}',
        );
    }

    protected function createController(
        CustomerPasskeyAssertionVerifierInterface $verifier,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        bool $enabled,
    ): PasskeyLoginVerifyController {
        return new PasskeyLoginVerifyController(
            verifier: $verifier,
            tokenStorage: $tokenStorage,
            router: $router,
            logger: $this->createStub(LoggerInterface::class),
            sessionLoginHandler: $this->createStub(CustomerSessionLoginHandlerInterface::class),
            enabled: $enabled,
        );
    }
}
