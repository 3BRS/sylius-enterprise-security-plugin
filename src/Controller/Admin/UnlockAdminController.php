<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\AdminUserLockoutManagerInterface;

class UnlockAdminController implements UnlockAdminControllerInterface
{
    use FlashHelperTrait;

    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserLockoutManagerInterface $lockoutManager,
        protected RouterInterface $router,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $user = $this->adminUserRepository->find($id);
        if (!$user instanceof LockableAdminUserInterface) {
            throw new NotFoundHttpException();
        }

        $this->lockoutManager->unlock($user);
        $this->addFlashMessage($request, 'success', 'three_brs.lockout.unlocked');

        return new RedirectResponse($this->router->generate('three_brs_admin_locked_admins'));
    }
}
