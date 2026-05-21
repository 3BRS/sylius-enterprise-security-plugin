<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\EnterpriseSecurityBundle\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractTwoFactorRegenerateRecoveryCodesController;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;

#[CoversClass(AbstractTwoFactorRegenerateRecoveryCodesController::class)]
class AbstractTwoFactorRegenerateRecoveryCodesControllerTest extends TestCase
{
    public function testRedirectsToLoginWhenTwoFactorNotEnabled(): void
    {
        $controller = $this->makeController(twoFactorEnabled: false);

        $response = $controller(new Request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
    }

    public function testRedirectsToDashboardWhenRecoveryCodesDisabled(): void
    {
        $controller = $this->makeController(recoveryCodesEnabled: false);

        $response = $controller(new Request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/dashboard', $response->getTargetUrl());
    }

    public function testThrowsBadRequestOnInvalidCsrf(): void
    {
        $controller = $this->makeController(csrfValid: false);

        $this->expectException(BadRequestHttpException::class);
        $controller(new Request());
    }

    public function testRegeneratesAndStoresInSession(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $controller = $this->makeController();
        $response = $controller($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/recovery', $response->getTargetUrl());
        self::assertSame(['a', 'b'], $request->getSession()->get('plain_codes_key'));
    }

    protected function makeController(
        bool $twoFactorEnabled = true,
        bool $recoveryCodesEnabled = true,
        bool $csrfValid = true,
    ): AbstractTwoFactorRegenerateRecoveryCodesController {
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($this->createStub(UserInterface::class));

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $generator = $this->createStub(RecoveryCodeGeneratorInterface::class);
        $generator->method('generate')->willReturn(['a', 'b']);

        $router = $this->createStub(RouterInterface::class);

        return new class($tokenStorage, $generator, $csrf, $router, $recoveryCodesEnabled, 2, $twoFactorEnabled) extends AbstractTwoFactorRegenerateRecoveryCodesController {
            public function __construct(
                TokenStorageInterface $tokenStorage,
                RecoveryCodeGeneratorInterface $generator,
                CsrfTokenManagerInterface $csrf,
                RouterInterface $router,
                bool $recoveryCodesEnabled,
                int $recoveryCodesCount,
                protected bool $twoFactorEnabled,
            ) {
                parent::__construct($tokenStorage, $generator, $csrf, $router, $recoveryCodesEnabled, $recoveryCodesCount);
            }

            protected function getCsrfTokenId(): string
            {
                return 'regen_csrf';
            }

            protected function isTwoFactorEnabledUser(UserInterface $user): bool
            {
                return $this->twoFactorEnabled;
            }

            protected function replaceRecoveryCodesAndCommit(UserInterface $user, array $plainCodes): void
            {
            }

            protected function getPlainCodesSessionKey(): string
            {
                return 'plain_codes_key';
            }

            protected function getLoginUrl(): string
            {
                return '/login';
            }

            protected function getDashboardUrl(): string
            {
                return '/dashboard';
            }

            protected function getRecoveryCodesUrl(): string
            {
                return '/recovery';
            }
        };
    }
}
