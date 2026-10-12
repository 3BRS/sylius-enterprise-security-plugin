<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\RateLimit\RateLimitGuardInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\TwoFactorDisableController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCodeInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;

/** @internal */
interface TestAdminUserForTwoFactorDisable extends AdminUserInterface, TwoFactorAuthAdminUserInterface
{
}

#[CoversClass(TwoFactorDisableController::class)]
class TwoFactorDisableControllerTest extends TestCase
{
    public function testAnUnusedRecoveryCodeConfirmsTheDisableWithoutBeingMarkedUsed(): void
    {
        $user = $this->createUser();

        $recoveryCode = $this->createMock(AdminUserRecoveryCodeInterface::class);
        $recoveryCode->expects(self::never())->method('setUsedAt');

        $repository = $this->createMock(AdminUserRecoveryCodeRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findUnusedByAdminUserAndHash')
            ->with($user, 'hash-of-A1B2-C3D4')
            ->willReturn($recoveryCode);
        $repository->expects(self::once())->method('deleteAllByAdminUser')->with($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);
        $rateLimitGuard->expects(self::once())
            ->method('consume')
            ->with(self::isInstanceOf(Request::class), 'admin', 'two_factor_code', 'admin@example.com');
        $rateLimitGuard->expects(self::once())->method('reset')->with('admin', 'two_factor_code', 'admin@example.com');

        $controller = $this->createController($user, $repository, $entityManager, $rateLimitGuard);

        $response = $controller($this->createRequest('A1B2-C3D4'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin', $response->getTargetUrl());
    }

    public function testAnUnknownCodeSendsTheAdministratorBackToTheTwoFactorPage(): void
    {
        $user = $this->createUser();

        $repository = $this->createMock(AdminUserRecoveryCodeRepositoryInterface::class);
        $repository->method('findUnusedByAdminUserAndHash')->willReturn(null);
        $repository->expects(self::never())->method('deleteAllByAdminUser');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);
        $rateLimitGuard->expects(self::once())->method('consume');
        $rateLimitGuard->expects(self::never())->method('reset');

        $controller = $this->createController($user, $repository, $entityManager, $rateLimitGuard);
        $request = $this->createRequest('000000');

        $response = $controller($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/two-factor/setup', $response->getTargetUrl());
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);
        self::assertSame(['three_brs.two_factor.confirmation_code_invalid'], $session->getFlashBag()->get('error'));
    }

    public function testARememberedSignInIsSentToTheSignInPageAndBroughtBackToTheTwoFactorPage(): void
    {
        $user = $this->createUser();

        $repository = $this->createMock(AdminUserRecoveryCodeRepositoryInterface::class);
        $repository->expects(self::never())->method('deleteAllByAdminUser');

        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);
        $rateLimitGuard->expects(self::never())->method('consume');

        $token = $this->createStub(RememberMeToken::class);
        $token->method('getUser')->willReturn($user);

        $controller = $this->createController($user, $repository, $this->createStub(EntityManagerInterface::class), $rateLimitGuard, $token);
        $request = $this->createRequest('123456');

        $response = $controller($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/login', $response->getTargetUrl());
        self::assertSame('/admin/two-factor/setup', $request->getSession()->get('_security.admin.target_path'));
    }

    private function createUser(): TestAdminUserForTwoFactorDisable
    {
        $user = $this->createStub(TestAdminUserForTwoFactorDisable::class);
        $user->method('getUserIdentifier')->willReturn('admin@example.com');
        $user->method('getRoles')->willReturn(['ROLE_ADMINISTRATION_ACCESS']);
        $user->method('isTotpAuthenticationEnabled')->willReturn(true);

        return $user;
    }

    private function createController(
        TestAdminUserForTwoFactorDisable $user,
        AdminUserRecoveryCodeRepositoryInterface $repository,
        EntityManagerInterface $entityManager,
        RateLimitGuardInterface $rateLimitGuard,
        ?TokenInterface $token = null,
    ): TwoFactorDisableController {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token ?? new UsernamePasswordToken($user, 'admin', ['ROLE_ADMINISTRATION_ACCESS']));

        $recoveryGenerator = $this->createStub(RecoveryCodeGeneratorInterface::class);
        $recoveryGenerator->method('hash')->willReturnCallback(static fn (string $code): string => 'hash-of-' . $code);

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnMap([
            ['sylius_admin_dashboard', [], RouterInterface::ABSOLUTE_PATH, '/admin'],
            ['sylius_admin_login', [], RouterInterface::ABSOLUTE_PATH, '/admin/login'],
            ['three_brs_admin_two_factor_setup', [], RouterInterface::ABSOLUTE_PATH, '/admin/two-factor/setup'],
        ]);

        $totpAuthenticator = $this->createStub(TotpAuthenticatorInterface::class);
        $totpAuthenticator->method('checkCode')->willReturn(false);

        return new TwoFactorDisableController(
            $entityManager,
            $repository,
            $tokenStorage,
            $csrfTokenManager,
            $router,
            $recoveryGenerator,
            $totpAuthenticator,
            $rateLimitGuard,
        );
    }

    private function createRequest(string $code): Request
    {
        $request = Request::create('/admin/two-factor/disable', 'POST', [
            '_csrf_token' => 'token',
            '_code' => $code,
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
