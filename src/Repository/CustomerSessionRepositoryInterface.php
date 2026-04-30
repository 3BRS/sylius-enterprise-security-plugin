<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;

interface CustomerSessionRepositoryInterface
{
    public function findOneBySessionId(string $sessionId): ?CustomerSessionInterface;

    /** @return list<CustomerSessionInterface> */
    public function findActiveForShopUser(ShopUserInterface $user): array;

    public function findActiveByIdForShopUser(int $id, ShopUserInterface $user): ?CustomerSessionInterface;
}
