<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
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
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\ForcePasswordChangeController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;
use Twig\Environment;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

#[CoversClass(ForcePasswordChangeController::class)]
class ForcePasswordChangeControllerTest extends TestCase
{
    public function testRedirectsToLoginWhenUserIsNotAnAdmin(): void
    {
        $controller = $this->createController(
            user: $this->createStub(UserInterface::class),
            passwordLoginEnabled: true,
            form: null,
        );

        $response = $controller(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/login', $response->getTargetUrl());
    }

    public function testRedirectsToDashboardWhenAdminPasswordLoginDisabled(): void
    {
        $controller = $this->createController(
            user: $this->createStub(AdminUserInterface::class),
            passwordLoginEnabled: false,
            form: null,
        );

        $response = $controller(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/dashboard', $response->getTargetUrl());
    }

    public function testRendersFormWhenAdminPasswordLoginEnabled(): void
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(false);

        $controller = $this->createController(
            user: $this->createStub(AdminUserInterface::class),
            passwordLoginEnabled: true,
            form: $form,
        );

        $response = $controller(self::request());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('force-password-change-form', (string) $response->getContent());
    }

    private function createController(
        ?object $user,
        bool $passwordLoginEnabled,
        ?FormInterface $form,
    ): ForcePasswordChangeController {
        $token = null;
        if ($user !== null) {
            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
        }

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        // The scope is the point: without naming it the test passes whichever switch
        // the controller consults, and it has to consult the admin one — this page is
        // the administrator's own forced password change.
        $checker = $this->createMock(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')
            ->with(SettingsScope::ADMIN)
            ->willReturn($passwordLoginEnabled);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => match ($route) {
            'sylius_admin_login' => '/admin/login',
            'sylius_admin_dashboard' => '/admin/dashboard',
            default => '/' . $route,
        });

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<form id="force-password-change-form"></form>');

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form ?? $this->createStub(FormInterface::class));

        return new ForcePasswordChangeController(
            $tokenStorage,
            $this->createStub(UserPasswordHasherInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $router,
            $twig,
            $formFactory,
            $checker,
        );
    }

    private static function request(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
