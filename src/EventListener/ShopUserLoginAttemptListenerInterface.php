<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

interface ShopUserLoginAttemptListenerInterface
{
    public function onLoginFailure(LoginFailureEvent $event): void;

    public function onLoginSuccess(LoginSuccessEvent $event): void;
}
