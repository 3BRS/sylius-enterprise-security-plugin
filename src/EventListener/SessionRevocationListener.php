<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        protected string $customerLoginRoute = 'sylius_shop_login',
        protected string $adminLoginRoute = 'sylius_admin_login',
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
                $event->setResponse($this->logoutAndRedirect($request, $this->customerLoginRoute));
            }

            return;
        }

        if ($this->adminEnabled && $user instanceof AdminUserInterface) {
            $session = $this->adminSessionRepository->findOneBySessionId($sessionId);
            if ($session !== null && $session->isRevoked()) {
                $event->setResponse($this->logoutAndRedirect($request, $this->adminLoginRoute));
            }
        }
    }

    protected function logoutAndRedirect(Request $request, string $loginRoute): Response
    {
        $request->getSession()->invalidate();
        $this->tokenStorage->setToken(null);

        // For JSON / AJAX clients, return a 401 so the caller can detect revocation
        // and react in code (e.g. force a full-page reload). Browsers following the
        // 302 redirect on a fetch() call would otherwise just get HTML back.
        if ($this->isJsonRequest($request)) {
            return new JsonResponse(
                ['error' => 'session_revoked'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return new RedirectResponse($this->router->generate($loginRoute));
    }

    protected function isJsonRequest(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        foreach ($request->getAcceptableContentTypes() as $contentType) {
            if (str_contains($contentType, 'json')) {
                return true;
            }
        }

        return false;
    }
}
