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
        protected string $apiRoute,
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // The API authenticates statelessly (JWT) per request and must behave as
        // if this plugin were not installed — never track a session for API logins
        // (they would otherwise pile up a phantom session row on every API call).
        if ($this->apiRoute !== '' && str_starts_with($event->getRequest()->getPathInfo(), $this->apiRoute)) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof AdminUserInterface) {
            return;
        }

        $this->handler->handle($user, $event->getRequest());
    }
}
