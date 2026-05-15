<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\LockedShopUserRepositoryInterface;
use Twig\Environment;

class LockedCustomersController implements LockedCustomersControllerInterface
{
    public function __construct(
        protected LockedShopUserRepositoryInterface $repository,
        protected Environment $twig,
        protected bool $enabled,
    ) {
    }

    public function __invoke(): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/Lockout/customers.html.twig',
            ['users' => $this->repository->findAllLocked()],
        ));
    }
}
