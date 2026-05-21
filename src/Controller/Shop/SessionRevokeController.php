<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractSessionRevokeController;
use ThreeBRS\EnterpriseSecurityBundle\Session\SessionRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;

class SessionRevokeController extends AbstractSessionRevokeController implements SessionRevokeControllerInterface
{
    public function __construct(
        protected CustomerSessionRepositoryInterface $repository,
        protected CustomerSessionTrackerInterface $tracker,
        TokenStorageInterface $tokenStorage,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        bool $enabled,
    ) {
        parent::__construct($tokenStorage, $csrfTokenManager, $router, $enabled);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
    }

    protected function findSessionForUser(int $id, UserInterface $user): ?SessionRecordInterface
    {
        \assert($user instanceof ShopUserInterface);

        return $this->repository->findActiveByIdForShopUser($id, $user);
    }

    protected function revokeSession(SessionRecordInterface $session): void
    {
        \assert($session instanceof CustomerSessionInterface);

        $this->tracker->revoke($session);
    }

    protected function getSessionsListUrl(Request $request): string
    {
        return $this->router->generate('three_brs_shop_sessions', ['_locale' => $request->getLocale()]);
    }
}
