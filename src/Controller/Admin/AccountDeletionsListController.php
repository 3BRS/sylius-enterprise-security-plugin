<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AccountDeletionsListController as BaseAccountDeletionsListController;

/**
 * See LockedUsersListController: the page lists customers who asked to be erased,
 * so the role it needs is declared here rather than left to the application's
 * access_control.
 */
#[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
class AccountDeletionsListController extends BaseAccountDeletionsListController implements AccountDeletionsListControllerInterface
{
}
