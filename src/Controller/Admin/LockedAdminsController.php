<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractLockedUsersListController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\LockedAdminUserRepositoryInterface;
use Twig\Environment;

class LockedAdminsController extends AbstractLockedUsersListController implements LockedAdminsControllerInterface
{
    public function __construct(
        protected LockedAdminUserRepositoryInterface $repository,
        Environment $twig,
        bool $enabled,
    ) {
        parent::__construct($twig, $enabled);
    }

    protected function findAllLockedUsers(): iterable
    {
        return $this->repository->findAllLocked();
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/Lockout/admins.html.twig';
    }
}
