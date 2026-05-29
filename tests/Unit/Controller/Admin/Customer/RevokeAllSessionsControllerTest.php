<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Admin\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer\RevokeAllSessionsController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;

#[CoversClass(RevokeAllSessionsController::class)]
class RevokeAllSessionsControllerTest extends TestCase
{
    public function testRevokesAllSessions(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($shopUser);

        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::once())->method('revokeAll')->with($shopUser);

        $controller = $this->createController($customer, $tracker, validToken: true);
        $response = $controller(self::request('valid-token'), 42);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testRejectsInvalidCsrfToken(): void
    {
        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('revokeAll');

        $controller = $this->createController(null, $tracker, validToken: false);

        $response = $controller(self::request('bad'), 42);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    private function createController(
        ?CustomerInterface $customer,
        CustomerSessionTrackerInterface $tracker,
        bool $validToken,
    ): RevokeAllSessionsController {
        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('find')->willReturn($customer);

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($validToken);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/admin/customers/42');

        return new RevokeAllSessionsController($customerRepository, $tracker, $csrf, $router);
    }

    private static function request(string $token): Request
    {
        $request = new Request(request: ['_csrf_token' => $token]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
