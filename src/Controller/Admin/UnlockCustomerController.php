<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\ShopUserLockoutManagerInterface;

class UnlockCustomerController implements UnlockCustomerControllerInterface
{
    use FlashHelperTrait;

    /** @param UserRepositoryInterface<ShopUserInterface> $shopUserRepository */
    public function __construct(
        protected UserRepositoryInterface $shopUserRepository,
        protected ShopUserLockoutManagerInterface $lockoutManager,
        protected RouterInterface $router,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $user = $this->shopUserRepository->find($id);
        if (!$user instanceof LockableShopUserInterface) {
            throw new NotFoundHttpException();
        }

        $this->lockoutManager->unlock($user);
        $this->addFlashMessage($request, 'success', 'three_brs.lockout.unlocked');

        return new RedirectResponse($this->router->generate('three_brs_admin_locked_customers'));
    }
}
