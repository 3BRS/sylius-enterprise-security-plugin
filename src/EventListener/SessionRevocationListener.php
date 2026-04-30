<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;

class SessionRevocationListener implements SessionRevocationListenerInterface
{
    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected CustomerSessionRepositoryInterface $customerSessionRepository,
        protected AdminUserSessionRepositoryInterface $adminSessionRepository,
        protected RouterInterface $router,
        protected bool $customerEnabled,
        protected bool $adminEnabled,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if (!$this->customerEnabled && !$this->adminEnabled) {
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

        if ($this->customerEnabled && $user instanceof ShopUserInterface) {
            $session = $this->customerSessionRepository->findOneBySessionId($sessionId);
            if ($session !== null && $session->isRevoked()) {
                $event->setResponse($this->logoutAndRedirect($request, 'sylius_shop_login'));
            }

            return;
        }

        if ($this->adminEnabled && $user instanceof AdminUserInterface) {
            $session = $this->adminSessionRepository->findOneBySessionId($sessionId);
            if ($session !== null && $session->isRevoked()) {
                $event->setResponse($this->logoutAndRedirect($request, 'sylius_admin_login'));
            }
        }
    }

    protected function logoutAndRedirect(Request $request, string $loginRoute): RedirectResponse
    {
        $request->getSession()->invalidate();
        $this->tokenStorage->setToken(null);

        return new RedirectResponse($this->router->generate($loginRoute));
    }
}
