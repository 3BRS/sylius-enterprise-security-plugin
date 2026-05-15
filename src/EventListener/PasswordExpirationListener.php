<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationCheckerInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;

class PasswordExpirationListener implements PasswordExpirationListenerInterface
{
    protected const SHOP_CHANGE_PASSWORD_ROUTE = 'sylius_shop_account_change_password';

    protected const ADMIN_CHANGE_PASSWORD_ROUTE = 'three_brs_admin_force_password_change';

    protected const EXCLUDED_ROUTES = [
        'sylius_shop_login',
        'sylius_shop_logout',
        'sylius_shop_register',
        'sylius_shop_request_password_reset_token',
        'sylius_shop_password_reset',
        'sylius_admin_login',
        'sylius_admin_logout',
        'sylius_admin_render_reset_password_page',
        'sylius_admin_request_password_reset',
        'sylius_admin_render_password_reset',
        'sylius_admin_password_reset',
        self::SHOP_CHANGE_PASSWORD_ROUTE,
        self::ADMIN_CHANGE_PASSWORD_ROUTE,
    ];

    protected const LIVE_COMPONENT_ACCEPT = 'application/vnd.live-component+html';

    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected PasswordExpirationCheckerInterface $checker,
        protected RouterInterface $router,
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
        $currentRoute = (string) $request->attributes->get('_route', '');

        if (str_starts_with($currentRoute, '_') || in_array($currentRoute, self::EXCLUDED_ROUTES, true)) {
            return;
        }

        // Fragment / AJAX requests (Symfony UX Live Components, XHR) must not be redirected —
        // the 302 would be consumed as an HTML fragment and break the page that issued the call.
        if ($this->isFragmentRequest($request)) {
            return;
        }

        // Users without a password (social login, magic link, passkey) are exempt from expiration.
        // If they later set a password, expiration starts from that moment via the password history listeners.
        if ($user instanceof PasswordAuthenticatedUserInterface && $user->getPassword() === null) {
            return;
        }

        if ($user instanceof PasswordExpirationShopUserInterface && $this->checker->isShopUserPasswordExpired($user)) {
            $session = $request->hasSession() ? $request->getSession() : null;
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('warning', 'three_brs.password_expired');
            }
            $url = $this->router->generate(self::SHOP_CHANGE_PASSWORD_ROUTE);
            $event->setResponse(new RedirectResponse($url));

            return;
        }

        if ($user instanceof PasswordExpirationAdminUserInterface && $this->checker->isAdminUserPasswordExpired($user)) {
            $url = $this->router->generate(self::ADMIN_CHANGE_PASSWORD_ROUTE);
            $event->setResponse(new RedirectResponse($url));
        }
    }

    protected function isFragmentRequest(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        return str_contains($request->headers->get('Accept', ''), self::LIVE_COMPONENT_ACCEPT);
    }
}
