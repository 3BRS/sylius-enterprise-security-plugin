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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer\CustomerPasswordLoginController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerLoginPreferenceInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerLoginPreferenceRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

#[CoversClass(CustomerPasswordLoginController::class)]
class CustomerPasswordLoginControllerTest extends TestCase
{
    public function testRejectsInvalidCsrfToken(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $guard = $this->createMock(LastAuthMethodGuardInterface::class);
        $guard->expects(self::never())->method('canDisablePasswordLoginForShopUser');

        $controller = $this->createController(
            customer: $this->customerWithUser(),
            validToken: false,
            guard: $guard,
            entityManager: $entityManager,
        );

        self::assertInstanceOf(RedirectResponse::class, $controller(self::request('bad', '0'), 42));
    }

    public function testThrows404WhenCustomerNotFound(): void
    {
        $controller = $this->createController(customer: null, validToken: true);

        $this->expectException(NotFoundHttpException::class);
        $controller(self::request('valid', '1'), 9999);
    }

    public function testRefusesToDisableWhenNoAlternativeSignInMethod(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $preferenceRepository = $this->createMock(CustomerLoginPreferenceRepositoryInterface::class);
        $preferenceRepository->expects(self::never())->method('findOneByShopUser');

        $guard = $this->createStub(LastAuthMethodGuardInterface::class);
        $guard->method('canDisablePasswordLoginForShopUser')->willReturn(false);

        $controller = $this->createController(
            customer: $this->customerWithUser(),
            validToken: true,
            preferenceRepository: $preferenceRepository,
            guard: $guard,
            entityManager: $entityManager,
        );

        self::assertInstanceOf(RedirectResponse::class, $controller(self::request('valid', '0'), 42));
    }

    public function testDisablesPasswordLoginWhenAnAlternativeExists(): void
    {
        $preference = $this->createMock(CustomerLoginPreferenceInterface::class);
        $preference->expects(self::once())->method('setPasswordLoginAllowed')->with(false);

        $preferenceRepository = $this->createStub(CustomerLoginPreferenceRepositoryInterface::class);
        $preferenceRepository->method('findOneByShopUser')->willReturn($preference);

        $guard = $this->createStub(LastAuthMethodGuardInterface::class);
        $guard->method('canDisablePasswordLoginForShopUser')->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $controller = $this->createController(
            customer: $this->customerWithUser(),
            validToken: true,
            preferenceRepository: $preferenceRepository,
            guard: $guard,
            entityManager: $entityManager,
        );

        self::assertInstanceOf(RedirectResponse::class, $controller(self::request('valid', '0'), 42));
    }

    public function testEnablingPasswordLoginCreatesAPreferenceWhenMissing(): void
    {
        $preferenceRepository = $this->createStub(CustomerLoginPreferenceRepositoryInterface::class);
        $preferenceRepository->method('findOneByShopUser')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        // Enabling never needs the lock-out guard.
        $guard = $this->createMock(LastAuthMethodGuardInterface::class);
        $guard->expects(self::never())->method('canDisablePasswordLoginForShopUser');

        $controller = $this->createController(
            customer: $this->customerWithUser(),
            validToken: true,
            preferenceRepository: $preferenceRepository,
            guard: $guard,
            entityManager: $entityManager,
        );

        self::assertInstanceOf(RedirectResponse::class, $controller(self::request('valid', '1'), 42));
    }

    protected function customerWithUser(): CustomerInterface
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getUser')->willReturn($this->createStub(ShopUserInterface::class));

        return $customer;
    }

    protected function createController(
        ?CustomerInterface $customer,
        bool $validToken,
        ?CustomerLoginPreferenceRepositoryInterface $preferenceRepository = null,
        ?LastAuthMethodGuardInterface $guard = null,
        ?EntityManagerInterface $entityManager = null,
    ): CustomerPasswordLoginController {
        $customerRepository = $this->createStub(CustomerRepositoryInterface::class);
        $customerRepository->method('find')->willReturn($customer);

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($validToken);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/admin/customers/42');

        return new CustomerPasswordLoginController(
            $customerRepository,
            $preferenceRepository ?? $this->createStub(CustomerLoginPreferenceRepositoryInterface::class),
            $guard ?? $this->createStub(LastAuthMethodGuardInterface::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $csrf,
            $router,
        );
    }

    protected static function request(string $token, string $allowed): Request
    {
        $request = new Request(request: ['_csrf_token' => $token, 'allowed' => $allowed]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
