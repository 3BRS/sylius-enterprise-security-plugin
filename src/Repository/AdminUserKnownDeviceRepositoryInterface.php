<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminUserKnownDeviceRepositoryInterface
{
    public function existsForAdminUser(AdminUserInterface $user, string $fingerprint): bool;
}
