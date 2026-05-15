<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableShopUserInterface;

class LockedShopUserRepository implements LockedShopUserRepositoryInterface
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected string $shopUserClass,
    ) {
    }

    public function findAllLocked(): array
    {
        /** @var list<ShopUserInterface> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from($this->shopUserClass, 'u')
            ->where('u.lockedAt IS NOT NULL')
            ->orderBy('u.lockedAt', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<ShopUserInterface&LockableShopUserInterface> $filtered */
        $filtered = array_values(array_filter(
            $result,
            static fn ($u): bool => $u instanceof LockableShopUserInterface,
        ));

        return $filtered;
    }
}
