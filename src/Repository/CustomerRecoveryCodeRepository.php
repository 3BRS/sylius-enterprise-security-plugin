<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCodeInterface;

/**
 * @extends ServiceEntityRepository<CustomerRecoveryCode>
 */
class CustomerRecoveryCodeRepository extends ServiceEntityRepository implements CustomerRecoveryCodeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerRecoveryCode::class);
    }

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
