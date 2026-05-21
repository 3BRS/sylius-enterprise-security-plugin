<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractUnlockUserController;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\ShopUserLockoutManagerInterface;

class UnlockCustomerController extends AbstractUnlockUserController implements UnlockCustomerControllerInterface
{
    protected const CSRF_TOKEN_ID = 'three_brs_unlock_customer';

    /** @param UserRepositoryInterface<ShopUserInterface> $shopUserRepository */
    public function __construct(
        protected UserRepositoryInterface $shopUserRepository,
        protected ShopUserLockoutManagerInterface $lockoutManager,
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
        return $this->router->generate('three_brs_admin_locked_customers');
    }

    protected function attemptUnlock(int $id): ?bool
    {
        $user = $this->shopUserRepository->find($id);
        if (!$user instanceof LockableShopUserInterface) {
            return null;
        }

        if (!$this->lockoutManager->isLocked($user)) {
            return false;
        }

        $this->lockoutManager->unlock($user);

        return true;
    }
}
