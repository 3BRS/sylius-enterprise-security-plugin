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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer\RevokeSessionController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;

#[CoversClass(RevokeSessionController::class)]
class RevokeSessionControllerTest extends TestCase
{
    public function testRevokesSessionWhenBelongsToCustomer(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($shopUser);

        $session = $this->createStub(CustomerSessionInterface::class);

        $sessionRepo = $this->createStub(CustomerSessionRepositoryInterface::class);
        $sessionRepo->method('findByIdAndShopUser')->willReturn($session);

        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::once())->method('revoke')->with($session);

        $controller = $this->createController($customer, $sessionRepo, $tracker, validToken: true);
        $response = $controller(self::request('valid-token'), 42, 7);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testRejectsInvalidCsrfToken(): void
    {
        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('revoke');

        $controller = $this->createController(
            null,
            $this->createStub(CustomerSessionRepositoryInterface::class),
            $tracker,
            validToken: false,
        );

        $this->expectException(BadRequestHttpException::class);
        $controller(self::request('bad'), 42, 7);
    }

    public function testThrows404WhenCustomerNotFound(): void
    {
        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('revoke');

        $controller = $this->createController(
            null,
            $this->createStub(CustomerSessionRepositoryInterface::class),
            $tracker,
            validToken: true,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller(self::request('valid-token'), 9999, 7);
    }

    public function testThrows404WhenCustomerHasNoShopUser(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn(null);

        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('revoke');

        $controller = $this->createController(
            $customer,
            $this->createStub(CustomerSessionRepositoryInterface::class),
            $tracker,
            validToken: true,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller(self::request('valid-token'), 42, 7);
    }

    public function testThrows404WhenSessionBelongsToOtherCustomer(): void
    {
        $shopUser = $this->createStub(ShopUserInterface::class);
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($shopUser);

        // findByIdAndShopUser scoped to this customer → returns null for foreign session.
        $sessionRepo = $this->createStub(CustomerSessionRepositoryInterface::class);
        $sessionRepo->method('findByIdAndShopUser')->willReturn(null);

        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('revoke');

        $controller = $this->createController($customer, $sessionRepo, $tracker, validToken: true);

        $this->expectException(NotFoundHttpException::class);
        $controller(self::request('valid-token'), 42, 7);
    }

    private function createController(
        ?CustomerInterface $customer,
        CustomerSessionRepositoryInterface $sessionRepository,
        CustomerSessionTrackerInterface $tracker,
        bool $validToken,
    ): RevokeSessionController {
        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('find')->willReturn($customer);

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($validToken);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/admin/customers/42');

        return new RevokeSessionController($customerRepository, $sessionRepository, $tracker, $csrf, $router);
    }

    private static function request(string $token): Request
    {
        $request = new Request(request: ['_csrf_token' => $token]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
