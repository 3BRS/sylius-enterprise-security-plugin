<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionTrackerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;

/**
 * Marks the active session row as revoked when a user signs out, so the
 * "Active sessions" list reflects reality. Without this, the DB row keeps
 * `revoked_at IS NULL` after sign-out and the row sticks around forever — every
 * subsequent login from the same browser creates an additional row, so the
 * list grows into a full login history instead of showing only currently-live
 * sessions.
 */
class SessionLogoutListener implements SessionLogoutListenerInterface
{
    public function __construct(
        protected CustomerSessionTrackerInterface $customerTracker,
        protected AdminUserSessionTrackerInterface $adminTracker,
        protected CustomerSessionRepositoryInterface $customerSessionRepository,
        protected AdminUserSessionRepositoryInterface $adminSessionRepository,
        protected bool $customerEnabled,
        protected bool $adminEnabled,
    ) {
    }

    public function onLogout(LogoutEvent $event): void
    {
        if (!$this->customerEnabled && !$this->adminEnabled) {
            return;
        }

        $token = $event->getToken();
        if ($token === null) {
            return;
        }
        $user = $token->getUser();

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }
        $sessionId = $request->getSession()->getId();
        if ($sessionId === '') {
            return;
        }

        if ($this->customerEnabled && $user instanceof ShopUserInterface) {
            $session = $this->customerSessionRepository->findOneBySessionId($sessionId);
            if ($session !== null) {
                $this->customerTracker->revoke($session);
            }

            return;
        }

        if ($this->adminEnabled && $user instanceof AdminUserInterface) {
            $session = $this->adminSessionRepository->findOneBySessionId($sessionId);
            if ($session !== null) {
                $this->adminTracker->revoke($session);
            }
        }
    }
}
