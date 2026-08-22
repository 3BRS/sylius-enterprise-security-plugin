<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ScopedFeatureCheckerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionTrackerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;

class SessionActivityListener implements SessionActivityListenerInterface
{
    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected CustomerSessionTrackerInterface $customerTracker,
        protected AdminUserSessionTrackerInterface $adminTracker,
        protected ScopedFeatureCheckerInterface $sessionManagement,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if (!$this->sessionManagement->isEnabled(SettingsScope::CUSTOMER) &&
            !$this->sessionManagement->isEnabled(SettingsScope::ADMIN)) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }
        $sessionId = $request->getSession()->getId();
        if ($sessionId === '') {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }
        $user = $token->getUser();

        if ($this->sessionManagement->isEnabled(SettingsScope::CUSTOMER) && $user instanceof ShopUserInterface) {
            $this->customerTracker->touch($sessionId);

            return;
        }
        if ($this->sessionManagement->isEnabled(SettingsScope::ADMIN) && $user instanceof AdminUserInterface) {
            $this->adminTracker->touch($sessionId);
        }
    }
}
