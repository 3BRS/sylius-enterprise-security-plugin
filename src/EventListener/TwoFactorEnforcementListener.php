<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\TwoFactorEnforcementCheckerInterface;

class TwoFactorEnforcementListener implements TwoFactorEnforcementListenerInterface
{
    private const SHOP_SETUP_ROUTE = 'three_brs_shop_two_factor_setup';

    private const ADMIN_SETUP_ROUTE = 'three_brs_admin_two_factor_setup';

    private const EXCLUDED_ROUTE_PREFIXES = [
        'sylius_shop_login',
        'sylius_shop_logout',
        'sylius_shop_register',
        'sylius_shop_request_password_reset',
        'sylius_shop_render_password_reset',
        'sylius_shop_password_reset',
        'sylius_shop_live_component',
        'sylius_shop_account_live_component',
        'sylius_admin_login',
        'sylius_admin_logout',
        'sylius_admin_render_reset_password',
        'sylius_admin_request_password_reset',
        'sylius_admin_render_password_reset',
        'sylius_admin_password_reset',
        'three_brs_shop_two_factor_',
        'three_brs_admin_two_factor_',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private TwoFactorEnforcementCheckerInterface $checker,
        private RouterInterface $router,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if ($user === null) {
            return;
        }

        $request = $event->getRequest();
        $currentRoute = $request->attributes->get('_route', '');

        if (str_starts_with($currentRoute, '_') || $this->isExcludedRoute($currentRoute)) {
            return;
        }

        if (
            $user instanceof ShopUserInterface &&
            $user instanceof TwoFactorAuthShopUserInterface &&
            $this->checker->shouldEnforceForShopUser($user)
        ) {
            $this->addFlash($request);
            $url = $this->router->generate(self::SHOP_SETUP_ROUTE);
            $event->setResponse(new RedirectResponse($url));

            return;
        }

        if (
            $user instanceof AdminUserInterface &&
            $user instanceof TwoFactorAuthAdminUserInterface &&
            $this->checker->shouldEnforceForAdminUser($user)
        ) {
            $this->addFlash($request);
            $url = $this->router->generate(self::ADMIN_SETUP_ROUTE);
            $event->setResponse(new RedirectResponse($url));
        }
    }

    private function isExcludedRoute(string $route): bool
    {
        foreach (self::EXCLUDED_ROUTE_PREFIXES as $excluded) {
            if ($route === $excluded || str_starts_with($route, $excluded)) {
                return true;
            }
        }

        return false;
    }

    private function addFlash(Request $request): void
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('warning', 'three_brs.two_factor.enforcement_required');
        }
    }
}
