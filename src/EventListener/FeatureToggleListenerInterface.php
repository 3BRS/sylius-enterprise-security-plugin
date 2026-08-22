<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\ControllerEvent;

interface FeatureToggleListenerInterface
{
    public function onKernelController(ControllerEvent $event): void;
}
