<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCodeInterface;

/**
 * @extends EntityRepository<AdminUserRecoveryCode>
 */
class AdminUserRecoveryCodeRepository extends EntityRepository implements AdminUserRecoveryCodeRepositoryInterface
{
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
