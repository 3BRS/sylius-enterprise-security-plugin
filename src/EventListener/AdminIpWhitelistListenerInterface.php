<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

interface AdminIpWhitelistListenerInterface
{
    /** Identity-independent pass, above the firewall. */
    public function onKernelRequestPreAuth(RequestEvent $event): void;

    /** Per-administrator pass, below the firewall. */
    public function onKernelRequest(RequestEvent $event): void;
}
