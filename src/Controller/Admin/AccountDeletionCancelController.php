<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractAccountDeletionCancelController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequest;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDeletionServiceInterface;

class AccountDeletionCancelController extends AbstractAccountDeletionCancelController implements AccountDeletionCancelControllerInterface
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
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
        $deletionRequest = $this->entityManager->find(CustomerDeletionRequest::class, $id);
        if (!$deletionRequest instanceof CustomerDeletionRequestInterface) {
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
