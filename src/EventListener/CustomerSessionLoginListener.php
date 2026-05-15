<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionLoginHandlerInterface;

class CustomerSessionLoginListener implements CustomerSessionLoginListenerInterface
{
    public function __construct(
        protected CustomerSessionLoginHandlerInterface $handler,
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof ShopUserInterface) {
            return;
        }

        $this->handler->handle($user, $event->getRequest());
    }
}
