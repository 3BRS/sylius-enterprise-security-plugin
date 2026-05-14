<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Shop;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop\SocialAccountsController;
use Twig\Environment;

#[CoversClass(SocialAccountsController::class)]
class SocialAccountsControllerTest extends TestCase
{
    public function testRendersAccountsTemplate(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/OAuth/accounts.html.twig')
            ->willReturn('<html>rendered</html>');

        $controller = new SocialAccountsController($twig);

        $response = $controller();

        self::assertSame(Response::class, $response::class);
        self::assertSame('<html>rendered</html>', $response->getContent());
    }
}
