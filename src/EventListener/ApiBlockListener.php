<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

/**
 * Makes the Sylius API disappear (404) for an audience whose two-factor
 * authentication is active.
 *
 * Two-factor authentication is a human, browser-based control — the user
 * proves a second factor during an interactive login. The API authenticates
 * with stateless JWT tokens minted from email + password alone;
 * Leaving it open would let anyone holding the password reach a 2FA-protected account
 * through the API, bypassing the second factor entirely.
 *
 * Therefore, when 2FA is active for an audience, its API simply does not exist:
 * every request is 404'd here at the kernel level (before the router and the
 * firewall), as if the API had never been installed for that audience.
 *
 *   - admin API (`%sylius.security.api_admin_route%`, default `/api/v2/admin`)
 *     is gated by the admin 2FA mode
 *   - shop API (`%sylius.security.api_shop_route%`, default `/api/v2/shop`)
 *     is gated by the customer 2FA mode
 *
 * "Active" means the 2FA mode is `allowed` or `enforced` (anything but
 * `disabled`), via the bundle's {@see FeatureToggleInterface::isTwoFactorActive()}.
 * The prefixes come from Sylius's own parameters, so the block follows the API
 * if it is relocated.
 */
class ApiBlockListener implements ApiBlockListenerInterface
{
    public function __construct(
        protected string $adminApiRoute,
        protected string $shopApiRoute,
        protected FeatureToggleInterface $features,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (
            $this->matchesRoute($path, $this->adminApiRoute) &&
            $this->features->isTwoFactorActive(SettingsScope::ADMIN)
        ) {
            throw new NotFoundHttpException('API access is disabled.');
        }

        if (
            $this->matchesRoute($path, $this->shopApiRoute) &&
            $this->features->isTwoFactorActive(SettingsScope::CUSTOMER)
        ) {
            throw new NotFoundHttpException('API access is disabled.');
        }
    }

    /**
     * Matches `/api/v2/admin` exactly and `/api/v2/admin/...`, but not
     * `/api/v2/admin-anything`, which a naive `str_starts_with` would catch.
     * An empty prefix never matches, so it can never block everything.
     */
    protected function matchesRoute(string $path, string $route): bool
    {
        if ($route === '') {
            return false;
        }

        return $path === $route || str_starts_with($path, $route . '/');
    }
}
