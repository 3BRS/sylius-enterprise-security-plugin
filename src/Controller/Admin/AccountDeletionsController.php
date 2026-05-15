<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;
use Twig\Environment;

class AccountDeletionsController implements AccountDeletionsControllerInterface
{
    public function __construct(
        protected CustomerDeletionRequestRepositoryInterface $repository,
        protected Environment $twig,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/AccountDeletion/index.html.twig',
            [
                'pendingRequests' => $this->repository->findPendingForAdmin(),
            ],
        ));
    }
}
