<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\SocialAccountsController;
use Twig\Environment;

#[CoversClass(SocialAccountsController::class)]
class SocialAccountsControllerTest extends TestCase
{
    public function testRedirectsToAdminLoginWhenUserIsNotAnAdmin(): void
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/admin/login');

        $controller = new SocialAccountsController(
            $tokenStorage,
            $router,
            $this->createStub(Environment::class),
        );

        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/login', $response->getTargetUrl());
    }

    public function testRendersAccountsTemplateWhenUserIsAdmin(): void
    {
        $admin = $this->createStub(AdminUserInterface::class);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($admin);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/OAuth/accounts.html.twig')
            ->willReturn('<html>rendered</html>');

        $controller = new SocialAccountsController(
            $tokenStorage,
            $this->createStub(RouterInterface::class),
            $twig,
        );

        $response = $controller();

        self::assertSame(Response::class, $response::class);
        self::assertSame('<html>rendered</html>', $response->getContent());
    }
}
