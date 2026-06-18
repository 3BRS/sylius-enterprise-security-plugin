<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\ApiBlockListener;

#[CoversClass(ApiBlockListener::class)]
class ApiBlockListenerTest extends TestCase
{
    private const ADMIN_ROUTE = '/api/v2/admin';

    private const SHOP_ROUTE = '/api/v2/shop';

    public function testBlocksAdminApiWhenAdminTwoFactorIsActive(): void
    {
        $listener = $this->createListener(adminTwoFactorActive: true, customerTwoFactorActive: false);

        $this->expectException(NotFoundHttpException::class);
        $listener->onKernelRequest($this->createEvent('/api/v2/admin/orders'));
    }

    public function testAllowsAdminApiWhenAdminTwoFactorIsInactive(): void
    {
        $listener = $this->createListener(adminTwoFactorActive: false, customerTwoFactorActive: false);

        $event = $this->createEvent('/api/v2/admin/orders');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testBlocksShopApiWhenCustomerTwoFactorIsActive(): void
    {
        $listener = $this->createListener(adminTwoFactorActive: false, customerTwoFactorActive: true);

        $this->expectException(NotFoundHttpException::class);
        $listener->onKernelRequest($this->createEvent('/api/v2/shop/account/orders'));
    }

    public function testAllowsShopApiWhenCustomerTwoFactorIsInactive(): void
    {
        $listener = $this->createListener(adminTwoFactorActive: false, customerTwoFactorActive: false);

        $event = $this->createEvent('/api/v2/shop/products');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAdminApiIsGatedByAdminScopeOnly(): void
    {
        // Customer 2FA on, admin 2FA off: the admin API must stay reachable.
        $listener = $this->createListener(adminTwoFactorActive: false, customerTwoFactorActive: true);

        $event = $this->createEvent('/api/v2/admin/orders');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAllowsApiPathOutsideAdminAndShop(): void
    {
        // Infrastructure paths (JSON-LD contexts, docs) are neither audience.
        $listener = $this->createListener(adminTwoFactorActive: true, customerTwoFactorActive: true);

        $event = $this->createEvent('/api/v2/contexts/Order');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAllowsPathThatMerelyStartsLikeRoute(): void
    {
        $listener = $this->createListener(adminTwoFactorActive: true, customerTwoFactorActive: true);

        $event = $this->createEvent('/api/v2/admin-docs');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNotConsultSettingsForNonApiPaths(): void
    {
        $features = $this->createMock(FeatureToggleInterface::class);
        $features->expects(self::never())->method('isTwoFactorActive');

        $listener = new ApiBlockListener(self::ADMIN_ROUTE, self::SHOP_ROUTE, $features);

        $event = $this->createEvent('/admin/dashboard');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testFollowsRelocatedApiRoutes(): void
    {
        $features = $this->createStub(FeatureToggleInterface::class);
        $features->method('isTwoFactorActive')->willReturn(true);

        $listener = new ApiBlockListener('/internal-api/admin', '/internal-api/shop', $features);

        $this->expectException(NotFoundHttpException::class);
        $listener->onKernelRequest($this->createEvent('/internal-api/admin/orders'));
    }

    public function testIgnoresSubRequests(): void
    {
        $listener = $this->createListener(adminTwoFactorActive: true, customerTwoFactorActive: true);

        $event = $this->createEvent('/api/v2/admin/orders', HttpKernelInterface::SUB_REQUEST);
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingWhenRoutesAreEmpty(): void
    {
        $features = $this->createStub(FeatureToggleInterface::class);
        $features->method('isTwoFactorActive')->willReturn(true);

        $listener = new ApiBlockListener('', '', $features);

        $event = $this->createEvent('/api/v2/admin/orders');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    private function createListener(bool $adminTwoFactorActive, bool $customerTwoFactorActive): ApiBlockListener
    {
        $features = $this->createStub(FeatureToggleInterface::class);
        $features->method('isTwoFactorActive')->willReturnMap([
            [SettingsScope::ADMIN, $adminTwoFactorActive],
            [SettingsScope::CUSTOMER, $customerTwoFactorActive],
        ]);

        return new ApiBlockListener(self::ADMIN_ROUTE, self::SHOP_ROUTE, $features);
    }

    private function createEvent(string $path, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path),
            $requestType,
        );
    }
}
