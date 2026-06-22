<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop\SetPasswordController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\CustomerPasswordLoginCheckerInterface;
use Twig\Environment;

#[CoversClass(SetPasswordController::class)]
class SetPasswordControllerTest extends TestCase
{
    public function testRedirectsToLoginWhenThereIsNoShopUser(): void
    {
        $controller = $this->createController(user: null, form: null, expectFlush: false);

        $response = $controller(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
    }

    public function testRedirectsToDashboardWhenPasswordLoginIsBlocked(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $controller = $this->createController(user: $user, form: null, expectFlush: false, passwordLoginBlocked: true);

        $response = $controller(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/account/dashboard', $response->getTargetUrl());
    }

    public function testRedirectsToChangePasswordWhenUserAlreadyHasPassword(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn('$2y$existing-hash');

        $controller = $this->createController(user: $user, form: null, expectFlush: false);

        $response = $controller(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/change-password', $response->getTargetUrl());
    }

    public function testRendersFormForPasswordlessUserOnGet(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(false);

        $controller = $this->createController(user: $user, form: $form, expectFlush: false);

        $response = $controller(self::request());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('set-password-form', (string) $response->getContent());
    }

    public function testSetsHashedPasswordAndRedirectsToDashboard(): void
    {
        $user = $this->createMock(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);
        $user->expects(self::once())->method('setPassword')->with('hashed-new-password');

        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['newPassword' => 'NewStrongPass1!']);

        $controller = $this->createController(
            user: $user,
            form: $form,
            expectFlush: true,
            hashedPassword: 'hashed-new-password',
        );

        $response = $controller(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/account/dashboard', $response->getTargetUrl());
    }

    public function testRendersFormAgainWhenSubmissionIsInvalid(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);

        $controller = $this->createController(user: $user, form: $form, expectFlush: false);

        $response = $controller(self::request());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('set-password-form', (string) $response->getContent());
    }

    private function createController(
        ?object $user,
        ?FormInterface $form,
        bool $expectFlush,
        string $hashedPassword = 'hashed',
        bool $passwordLoginBlocked = false,
    ): SetPasswordController {
        $token = null;
        if ($user !== null) {
            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
        }

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $passwordLoginChecker = $this->createStub(CustomerPasswordLoginCheckerInterface::class);
        $passwordLoginChecker->method('isPasswordLoginBlocked')->willReturn($passwordLoginBlocked);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn($hashedPassword);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($expectFlush ? self::once() : self::never())->method('flush');

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => match ($route) {
            'sylius_shop_login' => '/login',
            'sylius_shop_account_change_password' => '/change-password',
            'sylius_shop_account_dashboard' => '/account/dashboard',
            default => '/' . $route,
        });

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<form id="set-password-form"></form>');

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form ?? $this->createStub(FormInterface::class));

        return new SetPasswordController($tokenStorage, $passwordLoginChecker, $passwordHasher, $entityManager, $router, $twig, $formFactory);
    }

    private static function request(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
