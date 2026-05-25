<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Admin\Customer;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer\ForcePasswordResetController;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;

/** @internal */
interface TestShopUserForcePassword extends \Sylius\Component\Core\Model\ShopUserInterface, PasswordExpirationShopUserInterface
{
}

#[CoversClass(ForcePasswordResetController::class)]
class ForcePasswordResetControllerTest extends TestCase
{
    public function testSetsForcePasswordChangeAndRedirects(): void
    {
        $shopUser = $this->createMock(TestShopUserForcePassword::class);
        $shopUser->expects(self::once())->method('setForcePasswordChange')->with(true);

        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($shopUser);

        $controller = $this->createController($customer, validToken: true, expectFlush: true);

        $response = $controller(self::request('valid-token'), 42);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/customers/42', $response->getTargetUrl());
    }

    public function testRejectsInvalidCsrfToken(): void
    {
        $controller = $this->createController(null, validToken: false, expectFlush: false);

        $this->expectException(BadRequestHttpException::class);
        $controller(self::request('invalid-token'), 42);
    }

    public function testThrows404WhenCustomerNotFound(): void
    {
        $controller = $this->createController(null, validToken: true, expectFlush: false);

        $this->expectException(NotFoundHttpException::class);
        $controller(self::request('valid-token'), 9999);
    }

    public function testThrows404WhenCustomerHasNoShopUser(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn(null);

        $controller = $this->createController($customer, validToken: true, expectFlush: false);

        $this->expectException(NotFoundHttpException::class);
        $controller(self::request('valid-token'), 42);
    }

    private function createController(
        ?CustomerInterface $customer,
        bool $validToken,
        bool $expectFlush,
    ): ForcePasswordResetController {
        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('find')->willReturn($customer);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($expectFlush ? self::once() : self::never())->method('flush');

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($validToken);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route, array $params = []): string => '/admin/customers/' . ($params['id'] ?? ''));

        return new ForcePasswordResetController($customerRepository, $em, $csrf, $router);
    }

    private static function request(string $token): Request
    {
        $request = new Request(request: ['_csrf_token' => $token]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
