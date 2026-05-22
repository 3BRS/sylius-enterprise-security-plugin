<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpRestriction\IpRestrictionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpBlacklistInterface;

interface AdminUserIpBlacklistRepositoryInterface extends IpRestrictionRepositoryInterface
{
    public function findOneByUser(UserInterface $user): ?AdminUserIpBlacklistInterface;

    public function findOneByAdminUser(AdminUserInterface $user): ?AdminUserIpBlacklistInterface;

    /**
     * @return list<AdminUserIpBlacklistInterface>
     */
    public function findAllEnabled(): array;
}
