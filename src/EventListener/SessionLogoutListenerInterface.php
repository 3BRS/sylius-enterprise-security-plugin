<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\Security\Http\Event\LogoutEvent;

interface SessionLogoutListenerInterface
{
    public function onLogout(LogoutEvent $event): void;
}
