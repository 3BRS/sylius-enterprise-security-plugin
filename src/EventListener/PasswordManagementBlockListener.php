<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

/**
 * Closes the forgotten-password pages (request + reset) of a scope whose password login is disabled.
 * Their links are already hidden, but the routes stay public in Sylius, so without this a bookmark or
 * a hand-crafted request could still hand out a password nobody can sign in with. Only web routes are
 * gated: the API is untouched, it behaves as if the plugin were not installed.
 */
class PasswordManagementBlockListener implements PasswordManagementBlockListenerInterface
{
    /**
     * The change-password page is deliberately left open: it hands out nothing on its own (it demands
     * the current password) and a password it did change could not be signed in with anyway.
     *
     * @var array<string, string> route => route to redirect to
     */
    protected const CUSTOMER_ROUTES = [
        'sylius_shop_request_password_reset_token' => 'sylius_shop_login',
        'sylius_shop_password_reset' => 'sylius_shop_login',
    ];

    /** @var array<string, string> route => route to redirect to */
    protected const ADMIN_ROUTES = [
        'sylius_admin_render_reset_password_page' => 'sylius_admin_login',
        'sylius_admin_request_password_reset' => 'sylius_admin_login',
        'sylius_admin_render_password_reset' => 'sylius_admin_login',
        'sylius_admin_password_reset' => 'sylius_admin_login',
    ];

    public function __construct(
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
        protected RouterInterface $router,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (!is_string($route)) {
            return;
        }

        if (isset(static::CUSTOMER_ROUTES[$route])) {
            $this->block($event, SettingsScope::CUSTOMER, static::CUSTOMER_ROUTES[$route]);

            return;
        }

        if (isset(static::ADMIN_ROUTES[$route])) {
            $this->block($event, SettingsScope::ADMIN, static::ADMIN_ROUTES[$route]);
        }
    }

    protected function block(RequestEvent $event, SettingsScope $scope, string $redirectRoute): void
    {
        if ($this->passwordLoginChecker->isEnabled($scope)) {
            return;
        }

        $session = $event->getRequest()->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'three_brs.password_login.password_management_disabled');
        }

        $event->setResponse(new RedirectResponse($this->router->generate($redirectRoute)));
    }
}
