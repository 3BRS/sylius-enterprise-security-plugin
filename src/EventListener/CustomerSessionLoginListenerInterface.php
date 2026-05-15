<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

interface CustomerSessionLoginListenerInterface
{
    public function onLoginSuccess(LoginSuccessEvent $event): void;

    public function handleLogin(ShopUserInterface $user, Request $request): void;
}
