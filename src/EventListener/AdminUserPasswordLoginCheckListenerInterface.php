<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\Security\Http\Event\CheckPassportEvent;

interface AdminUserPasswordLoginCheckListenerInterface
{
    public function onCheckPassport(CheckPassportEvent $event): void;
}
