<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Shop;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop\SocialAccountsController;
use Twig\Environment;

#[CoversClass(SocialAccountsController::class)]
class SocialAccountsControllerTest extends TestCase
{
    public function testRedirectsToShopLoginWhenUserIsNotAShopUser(): void
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/en_US/login');

        $controller = new SocialAccountsController(
            $tokenStorage,
            $router,
            $this->createStub(Environment::class),
        );

        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/en_US/login', $response->getTargetUrl());
    }

    public function testRendersAccountsTemplateWhenUserIsShopUser(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($shopUser);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/OAuth/accounts.html.twig')
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
