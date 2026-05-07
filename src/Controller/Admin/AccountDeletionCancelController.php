<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequest;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDeletionServiceInterface;

class AccountDeletionCancelController implements AccountDeletionCancelControllerInterface
{
    use FlashHelperTrait;

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected CustomerDeletionServiceInterface $deletionService,
        protected CsrfTokenManagerInterface $csrfTokenManager,
        protected TokenStorageInterface $tokenStorage,
        protected RouterInterface $router,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('three_brs_account_deletion_cancel_' . $id, (string) $request->request->get('_token')))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $deletionRequest = $this->entityManager->find(CustomerDeletionRequest::class, $id);
        if (!$deletionRequest instanceof CustomerDeletionRequestInterface) {
            throw new NotFoundHttpException();
        }

        $admin = $this->tokenStorage->getToken()?->getUser();
        if (!$admin instanceof AdminUserInterface) {
            throw new NotFoundHttpException();
        }

        $this->deletionService->cancelByAdmin($deletionRequest, $admin);

        $this->addFlashMessage($request, 'success', 'three_brs.account_deletion.cancelled');

        return new RedirectResponse($this->router->generate('three_brs_admin_account_deletions'));
    }
}
