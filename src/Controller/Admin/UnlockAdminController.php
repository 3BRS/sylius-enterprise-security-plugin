<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractUnlockUserController;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\AdminUserLockoutManagerInterface;

class UnlockAdminController extends AbstractUnlockUserController implements UnlockAdminControllerInterface
{
    protected const CSRF_TOKEN_ID = 'three_brs_unlock_admin';

    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserLockoutManagerInterface $lockoutManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        bool $enabled,
    ) {
        parent::__construct($csrfTokenManager, $router, $enabled);
    }

    protected function getCsrfTokenId(): string
    {
        return self::CSRF_TOKEN_ID;
    }

    protected function getLockedListUrl(): string
    {
        return $this->router->generate('three_brs_admin_locked_admins');
    }

    protected function attemptUnlock(int $id): ?bool
    {
        $user = $this->adminUserRepository->find($id);
        if (!$user instanceof LockableAdminUserInterface) {
            return null;
        }

        if (!$this->lockoutManager->isLocked($user)) {
            return false;
        }

        $this->lockoutManager->unlock($user);

        return true;
    }
}
