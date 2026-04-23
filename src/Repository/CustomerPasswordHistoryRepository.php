<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasswordHistory;

/**
 * @extends ServiceEntityRepository<CustomerPasswordHistory>
 */
class CustomerPasswordHistoryRepository extends ServiceEntityRepository implements CustomerPasswordHistoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerPasswordHistory::class);
    }

    public function findRecentByShopUser(ShopUserInterface $user, int $count): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.shopUser = :user')
            ->setParameter('user', $user)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($count)
            ->getQuery()
            ->getResult()
        ;
    }

    public function deleteOldEntriesForShopUser(ShopUserInterface $user, int $keepCount): void
    {
        if ($keepCount === 0) {
            $this->createQueryBuilder('h')
                ->delete()
                ->where('h.shopUser = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->execute()
            ;

            return;
        }

        $idsToKeep = $this->createQueryBuilder('h')
            ->select('h.id')
            ->where('h.shopUser = :user')
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
            ->where('h.shopUser = :user')
            ->andWhere('h.id NOT IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $idsToKeep)
            ->getQuery()
            ->execute()
        ;
    }
}
