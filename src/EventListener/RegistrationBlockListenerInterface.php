<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;

interface RegistrationBlockListenerInterface
{
    public function onKernelRequest(RequestEvent $event): void;
}
