<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractSessionRevokeController;
use ThreeBRS\EnterpriseSecurityBundle\Session\SessionRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionTrackerInterface;

class SessionRevokeController extends AbstractSessionRevokeController implements SessionRevokeControllerInterface
{
    public function __construct(
        protected AdminUserSessionRepositoryInterface $repository,
        protected AdminUserSessionTrackerInterface $tracker,
        TokenStorageInterface $tokenStorage,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        bool $enabled,
    ) {
        parent::__construct($tokenStorage, $csrfTokenManager, $router, $enabled);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface;
    }

    protected function findSessionForUser(int $id, UserInterface $user): ?SessionRecordInterface
    {
        \assert($user instanceof AdminUserInterface);

        return $this->repository->findActiveByIdForAdminUser($id, $user);
    }

    protected function revokeSession(SessionRecordInterface $session): void
    {
        \assert($session instanceof AdminUserSessionInterface);

        $this->tracker->revoke($session);
    }

    protected function getSessionsListUrl(Request $request): string
    {
        return $this->router->generate('three_brs_admin_sessions');
    }
}
