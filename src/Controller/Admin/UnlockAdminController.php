<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\AdminUserLockoutManagerInterface;

class UnlockAdminController implements UnlockAdminControllerInterface
{
    use FlashHelperTrait;

    protected const CSRF_TOKEN_ID = 'three_brs_unlock_admin';

    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserLockoutManagerInterface $lockoutManager,
        protected RouterInterface $router,
        protected CsrfTokenManagerInterface $csrfTokenManager,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $user = $this->adminUserRepository->find($id);
        if (!$user instanceof LockableAdminUserInterface) {
            throw new NotFoundHttpException();
        }

        if (!$this->lockoutManager->isLocked($user)) {
            $this->addFlashMessage($request, 'info', 'three_brs.lockout.already_unlocked');

            return new RedirectResponse($this->router->generate('three_brs_admin_locked_admins'));
        }

        $this->lockoutManager->unlock($user);
        $this->addFlashMessage($request, 'success', 'three_brs.lockout.unlocked');

        return new RedirectResponse($this->router->generate('three_brs_admin_locked_admins'));
    }
}
