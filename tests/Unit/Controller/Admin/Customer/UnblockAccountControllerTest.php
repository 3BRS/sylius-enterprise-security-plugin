<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Admin\Customer;

use Doctrine\ORM\EntityManagerInterface;
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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer\UnblockAccountController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;

#[CoversClass(UnblockAccountController::class)]
class UnblockAccountControllerTest extends TestCase
{
    public function testEnablesUserAndRedirects(): void
    {
        $shopUser = $this->createMock(ShopUserInterface::class);
        $shopUser->expects(self::once())->method('setEnabled')->with(true);

        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($shopUser);

        $controller = $this->createController($customer, validToken: true, expectFlush: true);

        $response = $controller(self::request('valid-token'), 42);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testRejectsInvalidCsrfToken(): void
    {
        $controller = $this->createController(null, validToken: false, expectFlush: false);

        $response = $controller(self::request('bad'), 42);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testRefusesToUnblockAnAccountScheduledForDeletion(): void
    {
        // `enabled = false` is the whole of how a scheduled erasure is enforced, so
        // enabling the user here would undo it silently: they sign in again, the
        // administrator believes the account is restored, and the cron anonymises
        // them on the scheduled day anyway.
        $shopUser = $this->createMock(ShopUserInterface::class);
        $shopUser->expects(self::never())->method('setEnabled');

        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($shopUser);

        $controller = $this->createController(
            $customer,
            validToken: true,
            expectFlush: false,
            pendingDeletion: $this->createStub(CustomerDeletionRequestInterface::class),
        );

        $response = $controller(self::request('valid-token'), 42);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    private function createController(
        ?CustomerInterface $customer,
        bool $validToken,
        bool $expectFlush,
        ?CustomerDeletionRequestInterface $pendingDeletion = null,
    ): UnblockAccountController {
        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('find')->willReturn($customer);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($expectFlush ? self::once() : self::never())->method('flush');

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($validToken);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/admin/customers/42');

        $deletionRequests = $this->createStub(CustomerDeletionRequestRepositoryInterface::class);
        $deletionRequests->method('findActiveForCustomer')->willReturn($pendingDeletion);

        return new UnblockAccountController($customerRepository, $em, $deletionRequests, $csrf, $router);
    }

    private static function request(string $token): Request
    {
        $request = new Request(request: ['_csrf_token' => $token]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
