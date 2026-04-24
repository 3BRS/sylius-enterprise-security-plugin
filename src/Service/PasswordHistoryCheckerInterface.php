<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;

interface PasswordHistoryCheckerInterface
{
    public function wasPasswordUsedByShopUser(ShopUserInterface $user, string $plainPassword, int $count): bool;

    public function wasPasswordUsedByAdminUser(AdminUserInterface $user, string $plainPassword, int $count): bool;
}
