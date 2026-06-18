<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Blocks every request to the Sylius API.
 *
 * The API authenticates with stateless JWT tokens, so it bypasses ALL of this
 * plugin's security controls — two-factor authentication, account lockout, IP
 * whitelist/blacklist, session tracking. None of those can be enforced on the
 * API, so the whole API surface is shut off here at the kernel level (404),
 * before the firewall ever runs.
 *
 * The blocked path prefix is injected from Sylius's own `sylius.security.api_route`
 * parameter (default `/api/v2`) — the very value the API firewall is built from —
 * so if the API address is changed, this block automatically follows it.
 */
class ApiBlockListener implements ApiBlockListenerInterface
{
    public function __construct(
        protected string $apiRoute,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Defensive: never block everything if the API route parameter is empty.
        if ($this->apiRoute === '') {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        // Match `/api/v2` exactly and `/api/v2/...` — but not `/api/v2-anything`,
        // which would otherwise slip past a naive `str_starts_with($path, '/api/v2')`.
        if ($path !== $this->apiRoute && !str_starts_with($path, $this->apiRoute . '/')) {
            return;
        }

        throw new NotFoundHttpException('API access is disabled.');
    }
}
