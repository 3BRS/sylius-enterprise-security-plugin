<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentParserInterface;
use Twig\Environment;

class SessionsController implements SessionsControllerInterface
{
    public function __construct(
        protected AdminUserSessionRepositoryInterface $repository,
        protected TokenStorageInterface $tokenStorage,
        protected UserAgentParserInterface $userAgentParser,
        protected Environment $twig,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof AdminUserInterface) {
            throw new AccessDeniedHttpException();
        }

        $sessions = $this->repository->findActiveForAdminUser($user);
        $currentSessionId = $request->hasSession() ? $request->getSession()->getId() : '';

        $rows = [];
        foreach ($sessions as $session) {
            $rows[] = [
                'session' => $session,
                'userAgent' => $this->userAgentParser->parse($session->getUserAgent()),
                'isCurrent' => $session->getSessionId() === $currentSessionId,
            ];
        }

        return new Response($this->twig->render('@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/Sessions/index.html.twig', [
            'rows' => $rows,
        ]));
    }
}
