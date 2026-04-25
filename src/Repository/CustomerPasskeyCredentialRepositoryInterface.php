<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredentialInterface;

interface CustomerPasskeyCredentialRepositoryInterface
{
    public function findOneByCredentialId(string $credentialId): ?CustomerPasskeyCredentialInterface;

    /** @return list<CustomerPasskeyCredentialInterface> */
    public function findAllByShopUser(ShopUserInterface $user): array;

    public function countByShopUser(ShopUserInterface $user): int;
}
