<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\Response;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\LockedAdminUserRepositoryInterface;
use Twig\Environment;

class LockedAdminsController implements LockedAdminsControllerInterface
{
    public function __construct(
        protected LockedAdminUserRepositoryInterface $repository,
        protected Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/Lockout/admins.html.twig',
            ['users' => $this->repository->findAllLocked()],
        ));
    }
}
