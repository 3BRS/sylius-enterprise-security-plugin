<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
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
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop\TwoFactorDisableController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCodeInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerRecoveryCodeRepositoryInterface;

/** @internal */
interface TestTwoFactorShopUser extends ShopUserInterface, TwoFactorAuthShopUserInterface
{
}

#[CoversClass(TwoFactorDisableController::class)]
class TwoFactorDisableControllerTest extends TestCase
{
    public function testAnUnusedRecoveryCodeConfirmsTheDisableWithoutBeingMarkedUsed(): void
    {
        $user = $this->createUser();

        $recoveryCode = $this->createMock(CustomerRecoveryCodeInterface::class);
        $recoveryCode->expects(self::never())->method('setUsedAt');

        $repository = $this->createMock(CustomerRecoveryCodeRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findUnusedByShopUserAndHash')
            ->with($user, 'hash-of-A1B2-C3D4')
            ->willReturn($recoveryCode);
        $repository->expects(self::once())->method('deleteAllByShopUser')->with($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);
        $rateLimitGuard->expects(self::once())
            ->method('consume')
            ->with(self::isInstanceOf(Request::class), 'customer', 'two_factor_code', 'john@example.com');
        $rateLimitGuard->expects(self::once())->method('reset')->with('customer', 'two_factor_code', 'john@example.com');

        $controller = $this->createController($user, $repository, $entityManager, $rateLimitGuard);

        $response = $controller($this->createRequest('A1B2-C3D4'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/account/dashboard', $response->getTargetUrl());
    }

    public function testAnUnknownCodeSendsTheCustomerBackToTheManagePage(): void
    {
        $user = $this->createUser();

        $repository = $this->createMock(CustomerRecoveryCodeRepositoryInterface::class);
        $repository->method('findUnusedByShopUserAndHash')->willReturn(null);
        $repository->expects(self::never())->method('deleteAllByShopUser');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);
        $rateLimitGuard->expects(self::once())->method('consume');
        $rateLimitGuard->expects(self::never())->method('reset');

        $controller = $this->createController($user, $repository, $entityManager, $rateLimitGuard);
        $request = $this->createRequest('000000');

        $response = $controller($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/account/two-factor/setup', $response->getTargetUrl());
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);
        self::assertSame(['three_brs.two_factor.confirmation_code_invalid'], $session->getFlashBag()->get('error'));
    }

    public function testARememberedSignInIsSentToTheSignInPageAndBroughtBackToTheTwoFactorPage(): void
    {
        $user = $this->createUser();

        $repository = $this->createMock(CustomerRecoveryCodeRepositoryInterface::class);
        $repository->expects(self::never())->method('deleteAllByShopUser');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $rateLimitGuard = $this->createMock(RateLimitGuardInterface::class);
        $rateLimitGuard->expects(self::never())->method('consume');

        $token = $this->createStub(RememberMeToken::class);
        $token->method('getUser')->willReturn($user);

        $controller = $this->createController($user, $repository, $entityManager, $rateLimitGuard, $token);
        $request = $this->createRequest('123456');

        $response = $controller($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
        self::assertSame('/account/two-factor/setup', $request->getSession()->get('_security.shop.target_path'));
    }

    public function testAFullSignInSavesNoTargetPath(): void
    {
        $user = $this->createUser();

        $repository = $this->createStub(CustomerRecoveryCodeRepositoryInterface::class);
        $repository->method('findUnusedByShopUserAndHash')->willReturn(null);

        $controller = $this->createController(
            $user,
            $repository,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(RateLimitGuardInterface::class),
        );
        $request = $this->createRequest('000000');

        $controller($request);

        self::assertFalse($request->getSession()->has('_security.shop.target_path'));
    }

    private function createUser(): TestTwoFactorShopUser
    {
        $user = $this->createStub(TestTwoFactorShopUser::class);
        $user->method('getUserIdentifier')->willReturn('john@example.com');
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $user->method('isTotpAuthenticationEnabled')->willReturn(true);

        return $user;
    }

    private function createController(
        TestTwoFactorShopUser $user,
        CustomerRecoveryCodeRepositoryInterface $repository,
        EntityManagerInterface $entityManager,
        RateLimitGuardInterface $rateLimitGuard,
        ?TokenInterface $token = null,
    ): TwoFactorDisableController {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token ?? new UsernamePasswordToken($user, 'shop', ['ROLE_USER']));

        $recoveryGenerator = $this->createStub(RecoveryCodeGeneratorInterface::class);
        $recoveryGenerator->method('hash')->willReturnCallback(static fn (string $code): string => 'hash-of-' . $code);

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnMap([
            ['sylius_shop_account_dashboard', [], RouterInterface::ABSOLUTE_PATH, '/account/dashboard'],
            ['sylius_shop_login', [], RouterInterface::ABSOLUTE_PATH, '/login'],
            ['three_brs_shop_two_factor_setup', [], RouterInterface::ABSOLUTE_PATH, '/account/two-factor/setup'],
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
        $request = Request::create('/account/two-factor/disable', 'POST', [
            '_csrf_token' => 'token',
            '_code' => $code,
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
