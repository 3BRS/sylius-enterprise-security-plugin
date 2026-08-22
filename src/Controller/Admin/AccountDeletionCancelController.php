<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractAccountDeletionCancelController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDeletionServiceInterface;

class AccountDeletionCancelController extends AbstractAccountDeletionCancelController implements AccountDeletionCancelControllerInterface
{
    public function __construct(
        protected CustomerDeletionRequestRepositoryInterface $deletionRequestRepository,
        protected CustomerDeletionServiceInterface $deletionService,
        protected TokenStorageInterface $tokenStorage,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        bool $enabled,
    ) {
        parent::__construct($csrfTokenManager, $router, $enabled);
    }

    protected function cancelDeletionRequest(int $id): bool
    {
        // Pending is part of the lookup: two tabs, a double submit or the cron
        // stamping completedAt between render and click all reach a request that
        // cancelByAdmin() refuses with a RuntimeException, and this controller has no
        // catch — the administrator got a 500 for clicking a button twice.
        $deletionRequest = $this->deletionRequestRepository->findPendingById($id);
        if ($deletionRequest === null) {
            return false;
        }

        $admin = $this->tokenStorage->getToken()?->getUser();
        if (!$admin instanceof AdminUserInterface) {
            return false;
        }

        $this->deletionService->cancelByAdmin($deletionRequest, $admin);

        return true;
    }

    protected function getDeletionsListUrl(): string
    {
        return $this->router->generate('three_brs_admin_account_deletions');
    }
}
