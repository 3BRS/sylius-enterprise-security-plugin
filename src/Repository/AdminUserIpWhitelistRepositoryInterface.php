<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpWhitelistInterface;

interface AdminUserIpWhitelistRepositoryInterface
{
    public function findOneByAdminUser(AdminUserInterface $user): ?AdminUserIpWhitelistInterface;
}
