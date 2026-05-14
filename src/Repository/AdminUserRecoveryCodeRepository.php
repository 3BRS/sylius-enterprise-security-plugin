<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCodeInterface;

/**
 * @extends ServiceEntityRepository<AdminUserRecoveryCode>
 */
class AdminUserRecoveryCodeRepository extends ServiceEntityRepository implements AdminUserRecoveryCodeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUserRecoveryCode::class);
    }

    public function findUnusedByAdminUserAndHash(AdminUserInterface $user, string $codeHash): ?AdminUserRecoveryCodeInterface
    {
        return $this->createQueryBuilder('c')
            ->where('c.adminUser = :user')
            ->andWhere('c.codeHash = :hash')
            ->andWhere('c.usedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('hash', $codeHash)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function deleteAllByAdminUser(AdminUserInterface $user): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.adminUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute()
        ;
    }
}
