<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\RateLimit\RateLimitGuardInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\CustomerPasswordLoginCheckListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\RateLimitListener;

#[CoversClass(RateLimitListener::class)]
class RateLimitListenerTest extends TestCase
{
    /**
     * The invariant rather than the list: whatever counts as a web password login
     * has to be rate limited too. The checkout inline sign-in was recognised as one
     * by CustomerPasswordLoginCheckListener while going unlimited here, which left
     * the endpoint without CSRF as the cheaper of the two to hammer.
     */
    public function testEveryWebPasswordLoginRouteIsRateLimited(): void
    {
        $webLoginRoutes = $this->readProtectedConstant(
            CustomerPasswordLoginCheckListener::class,
            'WEB_LOGIN_CHECK_ROUTES',
        );
        $rateLimited = $this->readProtectedConstant(RateLimitListener::class, 'ROUTE_MAP');

        self::assertNotEmpty($webLoginRoutes);
        foreach ($webLoginRoutes as $route) {
            self::assertArrayHasKey(
                $route,
                $rateLimited,
                sprintf('Route "%s" authenticates a password on the web but consumes no limiter.', $route),
            );
        }
    }

    public function testKeysTheJsonLoginOnTheUsernameFromTheJsonBody(): void
    {
        $seen = null;
        $guard = $this->createStub(RateLimitGuardInterface::class);
        $guard->method('consume')->willReturnCallback(
            function (Request $request, string $group, string $action, ?string $identifier) use (&$seen): void {
                $seen = [$group, $action, $identifier];
            },
        );

        $event = $this->makeJsonEvent('sylius_shop_json_login_check', ['_username' => 'Buyer@Example.com']);
        $this->makeListener($guard)->onKernelRequest($event);

        // Same bucket and same key as the form login, so an admin unlock clears both.
        self::assertSame(['customer', 'login', 'Buyer@Example.com'], $seen);
        self::assertNull($event->getResponse());
    }

    public function testAnswersAThrottledJsonLoginWithA429(): void
    {
        $guard = $this->createStub(RateLimitGuardInterface::class);
        $guard->method('consume')->willThrowException(new TooManyRequestsHttpException(60));

        $event = $this->makeJsonEvent('sylius_shop_json_login_check', ['_username' => 'buyer@example.com']);
        $this->makeListener($guard)->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    public function testStillRedirectsAThrottledFormLogin(): void
    {
        $guard = $this->createStub(RateLimitGuardInterface::class);
        $guard->method('consume')->willThrowException(new TooManyRequestsHttpException(60));

        $request = Request::create('/en_US/login-check', 'POST', ['_username' => 'buyer@example.com']);
        $request->attributes->set('_route', 'sylius_shop_login_check');
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->makeListener($guard)->onKernelRequest($event);

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function testSurvivesAMalformedJsonBody(): void
    {
        $seen = 'untouched';
        $guard = $this->createStub(RateLimitGuardInterface::class);
        $guard->method('consume')->willReturnCallback(
            function (Request $request, string $group, string $action, ?string $identifier) use (&$seen): void {
                $seen = $identifier;
            },
        );

        // This listener runs above the firewall, so a throw here would be a 500 any
        // anonymous caller could trigger with one broken brace.
        $request = Request::create(
            '/en_US/json-login-check',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"_username": ',
        );
        $request->attributes->set('_route', 'sylius_shop_json_login_check');
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->makeListener($guard)->onKernelRequest($event);

        self::assertNull($seen, 'A body that does not decode should leave the key on the client address.');
    }

    public function testBoundsAnOverlongIdentifier(): void
    {
        $seen = null;
        $guard = $this->createStub(RateLimitGuardInterface::class);
        $guard->method('consume')->willReturnCallback(
            function (Request $request, string $group, string $action, ?string $identifier) use (&$seen): void {
                $seen = $identifier;
            },
        );

        $event = $this->makeJsonEvent('sylius_shop_json_login_check', ['_username' => str_repeat('a', 5000)]);
        $this->makeListener($guard)->onKernelRequest($event);

        self::assertIsString($seen);
        self::assertSame(255, mb_strlen($seen));
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function makeJsonEvent(string $route, array $payload): RequestEvent
    {
        $request = Request::create(
            '/en_US/json-login-check',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode($payload),
        );
        $request->attributes->set('_route', $route);

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    protected function makeListener(RateLimitGuardInterface $guard): RateLimitListener
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/login');

        return new RateLimitListener($guard, $router);
    }

    /**
     * @param class-string $class
     *
     * @return array<mixed>
     */
    protected function readProtectedConstant(string $class, string $name): array
    {
        /** @var array<mixed> $value */
        $value = (new \ReflectionClass($class))->getConstant($name);

        return $value;
    }
}
