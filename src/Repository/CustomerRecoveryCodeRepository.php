<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCodeInterface;

/**
 * @extends EntityRepository<CustomerRecoveryCode>
 */
class CustomerRecoveryCodeRepository extends EntityRepository implements CustomerRecoveryCodeRepositoryInterface
{
    public function findUnusedByShopUserAndHash(ShopUserInterface $user, string $codeHash): ?CustomerRecoveryCodeInterface
    {
        return $this->createQueryBuilder('c')
            ->where('c.shopUser = :user')
            ->andWhere('c.codeHash = :hash')
            ->andWhere('c.usedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('hash', $codeHash)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findAllByShopUser(ShopUserInterface $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.shopUser = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function deleteAllByShopUser(ShopUserInterface $user): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.shopUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute()
        ;
    }
}
