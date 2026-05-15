<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

interface AdminUserSessionLoginListenerInterface
{
    public function onLoginSuccess(LoginSuccessEvent $event): void;

    public function handleLogin(AdminUserInterface $user, Request $request): void;
}
