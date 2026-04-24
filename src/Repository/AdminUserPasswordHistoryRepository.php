<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasswordHistory;

/**
 * @extends EntityRepository<AdminUserPasswordHistory>
 */
class AdminUserPasswordHistoryRepository extends EntityRepository implements AdminUserPasswordHistoryRepositoryInterface
{
    public function findRecentByAdminUser(AdminUserInterface $user, int $count): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.adminUser = :user')
            ->setParameter('user', $user)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($count)
            ->getQuery()
            ->getResult()
        ;
    }

    public function deleteOldEntriesForAdminUser(AdminUserInterface $user, int $keepCount): void
    {
        if ($keepCount === 0) {
            $this->createQueryBuilder('h')
                ->delete()
                ->where('h.adminUser = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->execute()
            ;

            return;
        }

        $idsToKeep = $this->createQueryBuilder('h')
            ->select('h.id')
            ->where('h.adminUser = :user')
            ->setParameter('user', $user)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($keepCount)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        if ($idsToKeep === []) {
            return;
        }

        $this->createQueryBuilder('h')
            ->delete()
            ->where('h.adminUser = :user')
            ->andWhere('h.id NOT IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $idsToKeep)
            ->getQuery()
            ->execute()
        ;
    }
}
