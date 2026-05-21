<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractLockedUsersListController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\LockedShopUserRepositoryInterface;
use Twig\Environment;

class LockedCustomersController extends AbstractLockedUsersListController implements LockedCustomersControllerInterface
{
    public function __construct(
        protected LockedShopUserRepositoryInterface $repository,
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
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/Lockout/customers.html.twig';
    }
}
