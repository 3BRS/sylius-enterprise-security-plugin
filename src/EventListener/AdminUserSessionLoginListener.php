<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionLoginHandlerInterface;

class AdminUserSessionLoginListener implements AdminUserSessionLoginListenerInterface
{
    public function __construct(
        protected AdminUserSessionLoginHandlerInterface $handler,
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof AdminUserInterface) {
            return;
        }

        $this->handler->handle($user, $event->getRequest());
    }
}
