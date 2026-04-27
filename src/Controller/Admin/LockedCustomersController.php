<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\Response;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\LockedShopUserRepositoryInterface;
use Twig\Environment;

class LockedCustomersController implements LockedCustomersControllerInterface
{
    public function __construct(
        protected LockedShopUserRepositoryInterface $repository,
        protected Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/Lockout/customers.html.twig',
            ['users' => $this->repository->findAllLocked()],
        ));
    }
}
