<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\ApiBlockListener;

#[CoversClass(ApiBlockListener::class)]
class ApiBlockListenerTest extends TestCase
{
    private function createEvent(string $path, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path),
            $requestType,
        );
    }

    public function testBlocksApiRootPath(): void
    {
        $listener = new ApiBlockListener('/api/v2');

        $this->expectException(NotFoundHttpException::class);
        $listener->onKernelRequest($this->createEvent('/api/v2'));
    }

    public function testBlocksAdminApiPath(): void
    {
        $listener = new ApiBlockListener('/api/v2');

        $this->expectException(NotFoundHttpException::class);
        $listener->onKernelRequest($this->createEvent('/api/v2/admin/orders'));
    }

    public function testBlocksShopApiPath(): void
    {
        $listener = new ApiBlockListener('/api/v2');

        $this->expectException(NotFoundHttpException::class);
        $listener->onKernelRequest($this->createEvent('/api/v2/shop/products'));
    }

    public function testAllowsNonApiPath(): void
    {
        $listener = new ApiBlockListener('/api/v2');

        $event = $this->createEvent('/admin/dashboard');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAllowsPathThatMerelyStartsLikeApiRoute(): void
    {
        $listener = new ApiBlockListener('/api/v2');

        $event = $this->createEvent('/api/v2-docs');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testFollowsRelocatedApiRoute(): void
    {
        // The prefix is injected from Sylius's api_route parameter, so moving the
        // API also moves the block — here the API now lives under /internal-api.
        $listener = new ApiBlockListener('/internal-api');

        $this->expectException(NotFoundHttpException::class);
        $listener->onKernelRequest($this->createEvent('/internal-api/admin/orders'));
    }

    public function testIgnoresSubRequests(): void
    {
        $listener = new ApiBlockListener('/api/v2');

        $event = $this->createEvent('/api/v2/admin/orders', HttpKernelInterface::SUB_REQUEST);
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testDoesNothingWhenApiRouteIsEmpty(): void
    {
        $listener = new ApiBlockListener('');

        $event = $this->createEvent('/api/v2/admin/orders');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }
}
