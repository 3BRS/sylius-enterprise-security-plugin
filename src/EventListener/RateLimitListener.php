<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RateLimit\RateLimitGuardInterface;

class RateLimitListener implements RateLimitListenerInterface
{
    /**
     * Map of route name → [group, action, fallbackRedirectRoute, usernameField (optional)].
     * When usernameField is provided, the rate-limit key is the username only — gives admin
     * unlock a deterministic key to reset. Otherwise the key falls back to the client IP.
     * fallbackRedirectRoute is the route used when the Referer header is missing or points
     * to a different host.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3?: string}>
     */
    protected const ROUTE_MAP = [
        'sylius_shop_login_check' => ['customer', 'login', 'sylius_shop_login', '_username'],
        'sylius_admin_login_check' => ['admin', 'login', 'sylius_admin_login', '_username'],
        'sylius_shop_request_password_reset_token' => ['customer', 'password_reset', 'sylius_shop_request_password_reset_token'],
        'sylius_admin_request_password_reset' => ['admin', 'password_reset', 'sylius_admin_request_password_reset'],
        'sylius_shop_register' => ['customer', 'register', 'sylius_shop_register'],
        'three_brs_shop_magic_link_request' => ['customer', 'magic_link', 'three_brs_shop_magic_link_request'],
        'three_brs_admin_magic_link_request' => ['admin', 'magic_link', 'three_brs_admin_magic_link_request'],
        // Account deletion POSTs the user's current password — same brute-force surface as
        // password reset, so it shares that limiter budget per (IP, action) pair.
        'three_brs_shop_account_deletion_request' => ['customer', 'password_reset', 'three_brs_shop_account_deletion_request'],
    ];

    public function __construct(
        protected RateLimitGuardInterface $guard,
        protected UrlGeneratorInterface $router,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('POST')) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');
        if (!isset(self::ROUTE_MAP[$route])) {
            return;
        }

        [$group, $action, $fallbackRoute] = self::ROUTE_MAP[$route];
        $usernameField = self::ROUTE_MAP[$route][3] ?? null;
        $userIdentifier = $usernameField === null ? null : $this->extractIdentifier($request, $usernameField);

        try {
            $this->guard->consume($request, $group, $action, $userIdentifier);
        } catch (TooManyRequestsHttpException) {
            $session = $request->hasSession() ? $request->getSession() : null;
            if ($session !== null && method_exists($session, 'getFlashBag')) {
                $session->getFlashBag()->add('error', 'three_brs.rate_limit.too_many_requests');
            }

            $event->setResponse(new RedirectResponse($this->resolveRedirectTarget($request, $fallbackRoute)));
        }
    }

    protected function extractIdentifier(Request $request, string $field): ?string
    {
        $value = $request->request->get($field);
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Use the Referer header if it points to the same host (so the user lands back on
     * the form they submitted), otherwise fall back to the route's canonical entry page.
     */
    protected function resolveRedirectTarget(Request $request, string $fallbackRoute): string
    {
        $referer = $request->headers->get('Referer', '');
        if ($referer !== '') {
            $refererHost = parse_url($referer, \PHP_URL_HOST);
            if ($refererHost === $request->getHost()) {
                return $referer;
            }
        }

        return $this->router->generate($fallbackRoute);
    }
}
