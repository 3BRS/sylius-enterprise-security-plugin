<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

interface ApiBlockListenerInterface
{
    public function onKernelRequest(RequestEvent $event): void;
}
