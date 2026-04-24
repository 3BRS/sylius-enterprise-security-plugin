<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasswordHistoryInterface;

interface CustomerPasswordHistoryRepositoryInterface
{
    /**
     * @return CustomerPasswordHistoryInterface[]
     */
    public function findRecentByShopUser(ShopUserInterface $user, int $count): array;

    public function deleteOldEntriesForShopUser(ShopUserInterface $user, int $keepCount): void;
}
