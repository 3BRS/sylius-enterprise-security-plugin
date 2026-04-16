<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCodeInterface;

interface CustomerRecoveryCodeRepositoryInterface
{
    public function findUnusedByShopUserAndHash(ShopUserInterface $user, string $codeHash): ?CustomerRecoveryCodeInterface;

    /**
     * @return array<int, CustomerRecoveryCodeInterface>
     */
    public function findAllByShopUser(ShopUserInterface $user): array;

    public function deleteAllByShopUser(ShopUserInterface $user): int;
}
