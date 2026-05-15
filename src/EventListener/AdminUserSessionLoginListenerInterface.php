<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

interface AdminUserSessionLoginListenerInterface
{
    public function onLoginSuccess(LoginSuccessEvent $event): void;
}
