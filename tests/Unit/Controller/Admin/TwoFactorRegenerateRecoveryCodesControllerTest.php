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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\RateLimit\RateLimitGuardInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\TwoFactorRegenerateRecoveryCodesController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\TwoFactorSetupController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;

/** @internal */
interface TestTwoFactorAdminUser extends AdminUserInterface, TwoFactorAuthAdminUserInterface
{
}

#[CoversClass(TwoFactorRegenerateRecoveryCodesController::class)]
class TwoFactorRegenerateRecoveryCodesControllerTest extends TestCase
{
    public function testTheCurrentTotpCodeConfirmsTheRegeneration(): void
    {
        $user = $this->createUser();

        $repository = $this->createMock(AdminUserRecoveryCodeRepositoryInterface::class);
        $repository->expects(self::never())->method('findUnusedByAdminUserAndHash');
        $repository->expects(self::once())->method('deleteAllByAdminUser')->with($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);
        $rateLimitGuard->expects(self::once())
            ->method('consume')
            ->with(self::isInstanceOf(Request::class), 'admin', 'two_factor_code', 'admin@example.com');
        $rateLimitGuard->expects(self::once())->method('reset')->with('admin', 'two_factor_code', 'admin@example.com');

        $controller = $this->createController($user, $repository, $entityManager, $rateLimitGuard, totpCodeValid: true);
        $request = $this->createRequest('123456');

        $response = $controller($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/two-factor/recovery-codes', $response->getTargetUrl());
        self::assertSame(['AAAA-1111', 'BBBB-2222'], $request->getSession()->get(TwoFactorSetupController::SESSION_PLAIN_RECOVERY_CODES));
    }

    public function testARefusedCodeKeepsTheRecoveryCodes(): void
    {
        $user = $this->createUser();

        $repository = $this->createMock(AdminUserRecoveryCodeRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findUnusedByAdminUserAndHash')
            ->with($user, 'hash-of-000000')
            ->willReturn(null);
        $repository->expects(self::never())->method('deleteAllByAdminUser');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);
        $rateLimitGuard->expects(self::once())->method('consume');
        $rateLimitGuard->expects(self::never())->method('reset');

        $controller = $this->createController($user, $repository, $entityManager, $rateLimitGuard, totpCodeValid: false);
        $request = $this->createRequest('000000');

        $response = $controller($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/two-factor/setup', $response->getTargetUrl());
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);
        self::assertSame(['three_brs.two_factor.confirmation_code_invalid'], $session->getFlashBag()->get('error'));
    }

    private function createUser(): TestTwoFactorAdminUser
    {
        $user = $this->createStub(TestTwoFactorAdminUser::class);
        $user->method('getUserIdentifier')->willReturn('admin@example.com');
        $user->method('getRoles')->willReturn(['ROLE_ADMINISTRATION_ACCESS']);
        $user->method('isTwoFactorEnabled')->willReturn(true);
        $user->method('isTotpAuthenticationEnabled')->willReturn(true);

        return $user;
    }

    private function createController(
        TestTwoFactorAdminUser $user,
        AdminUserRecoveryCodeRepositoryInterface $repository,
        EntityManagerInterface $entityManager,
        RateLimitGuardInterface $rateLimitGuard,
        bool $totpCodeValid,
    ): TwoFactorRegenerateRecoveryCodesController {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'admin', ['ROLE_ADMINISTRATION_ACCESS']));

        $recoveryGenerator = $this->createStub(RecoveryCodeGeneratorInterface::class);
        $recoveryGenerator->method('hash')->willReturnCallback(static fn (string $code): string => 'hash-of-' . $code);
        $recoveryGenerator->method('generate')->willReturn(['AAAA-1111', 'BBBB-2222']);

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnMap([
            ['three_brs_admin_two_factor_recovery_codes', [], RouterInterface::ABSOLUTE_PATH, '/admin/two-factor/recovery-codes'],
            ['three_brs_admin_two_factor_setup', [], RouterInterface::ABSOLUTE_PATH, '/admin/two-factor/setup'],
        ]);

        $settings = $this->createStub(SettingsProviderInterface::class);
        $settings->method('getBool')->willReturn(true);
        $settings->method('getInt')->willReturn(2);

        $totpAuthenticator = $this->createStub(TotpAuthenticatorInterface::class);
        $totpAuthenticator->method('checkCode')->willReturn($totpCodeValid);

        return new TwoFactorRegenerateRecoveryCodesController(
            $entityManager,
            $repository,
            $tokenStorage,
            $recoveryGenerator,
            $csrfTokenManager,
            $router,
            $settings,
            $totpAuthenticator,
            $rateLimitGuard,
        );
    }

    private function createRequest(string $code): Request
    {
        $request = Request::create('/admin/two-factor/recovery-codes/regenerate', 'POST', [
            '_csrf_token' => 'token',
            '_code' => $code,
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
