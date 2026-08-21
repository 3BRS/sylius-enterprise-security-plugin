<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpWhitelist\IpWhitelistCheckerInterface;

class AdminIpWhitelistListener implements AdminIpWhitelistListenerInterface
{
    public function __construct(
        protected IpWhitelistCheckerInterface $checker,
        protected TokenStorageInterface $tokenStorage,
        protected string $adminPathPrefix = '/admin',
    ) {
    }

    /**
     * Runs above the firewall, so it sees the requests the firewall answers itself —
     * `/admin/login-check`, `/admin/2fa_check`, `/admin/logout`. On those the
     * authenticator always sets a response, and setResponse() stops propagation, so
     * the post-authentication pass below never used to run and a blocked address
     * could still post to the login form: enough to drive an administrator's lockout
     * counter, which docs/admin-ip-blacklist.md offers this feature to prevent.
     *
     * Only the identity-independent half of the decision belongs here. The token
     * storage is still empty at this priority, and calling isAllowedForAdmin() with
     * no user would lock out precisely those administrators whose access rests on a
     * personal entry rather than the global list.
     */
    public function onKernelRequestPreAuth(RequestEvent $event): void
    {
        if (!$this->appliesTo($event)) {
            return;
        }

        $ip = (string) $event->getRequest()->getClientIp();
        if ($this->checker->isAllowedAnonymously($ip)) {
            return;
        }

        $event->setResponse($this->denied());
    }

    /**
     * The second pass, below the firewall, where the authenticated administrator is
     * known and the check can be narrowed from "any enabled entry covers this
     * address" to "this administrator's entry does". Without it an address listed
     * for administrator A would let an attacker sign in as administrator B.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->appliesTo($event)) {
            return;
        }

        $request = $event->getRequest();
        $ip = (string) $request->getClientIp();

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        $allowed = $user instanceof AdminUserInterface
            ? $this->checker->isAllowedForAdmin($user, $ip)
            : $this->checker->isAllowedAnonymously($ip);

        if ($allowed) {
            return;
        }

        $event->setResponse($this->denied());
    }

    protected function appliesTo(RequestEvent $event): bool
    {
        if (!$event->isMainRequest()) {
            return false;
        }

        if (!$this->checker->isFeatureEnabled()) {
            return false;
        }

        $path = $event->getRequest()->getPathInfo();

        // Match `/admin` exactly and `/admin/...` — but not `/admin-anything`,
        // which would otherwise slip past a naive `str_starts_with($path, '/admin')`.
        return $path === $this->adminPathPrefix || str_starts_with($path, $this->adminPathPrefix . '/');
    }

    protected function denied(): Response
    {
        return new Response(
            'Access denied',
            Response::HTTP_FORBIDDEN,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
