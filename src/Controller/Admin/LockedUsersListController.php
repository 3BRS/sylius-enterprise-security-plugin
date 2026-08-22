<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use ThreeBRS\EnterpriseSecurityBundle\Controller\LockedUsersListController as BaseLockedUsersListController;

/**
 * The page lists other people's accounts, so it states the role it needs rather
 * than resting on the application's access_control, which the plugin neither
 * ships nor can see. The bundle class carries no attribute of its own — a bundle
 * knows nothing about Sylius roles — and PHP does not inherit class attributes,
 * so the plugin declares one here and wires this class in its place.
 */
#[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
class LockedUsersListController extends BaseLockedUsersListController implements LockedUsersListControllerInterface
{
}
