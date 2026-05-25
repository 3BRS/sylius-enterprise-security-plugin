<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractSessionRevokeOthersController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionTrackerInterface;

class SessionRevokeOthersController extends AbstractSessionRevokeOthersController implements SessionRevokeOthersControllerInterface
{
    public function __construct(
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

    protected function revokeOtherSessions(string $currentSessionId, UserInterface $user): void
    {
        \assert($user instanceof AdminUserInterface);

        $this->tracker->revokeOthers($currentSessionId, $user);
    }

    protected function getSessionsListUrl(Request $request): string
    {
        return $this->router->generate('three_brs_admin_sessions');
    }
}
