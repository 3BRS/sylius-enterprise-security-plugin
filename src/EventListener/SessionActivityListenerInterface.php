<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

interface SessionActivityListenerInterface
{
    public function onKernelRequest(RequestEvent $event): void;
}
