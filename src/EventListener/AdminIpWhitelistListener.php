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

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->checker->isFeatureEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Match `/admin` exactly and `/admin/...` — but not `/admin-anything`,
        // which would otherwise slip past a naive `str_starts_with($path, '/admin')`.
        if ($path !== $this->adminPathPrefix && !str_starts_with($path, $this->adminPathPrefix . '/')) {
            return;
        }

        $ip = (string) $request->getClientIp();

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        $allowed = $user instanceof AdminUserInterface
            ? $this->checker->isAllowedForAdmin($user, $ip)
            : $this->checker->isAllowedAnonymously($ip);

        if ($allowed) {
            return;
        }

        $event->setResponse(new Response(
            'Access denied',
            Response::HTTP_FORBIDDEN,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        ));
    }
}
