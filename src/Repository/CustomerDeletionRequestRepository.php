<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\CustomerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequest;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;

/**
 * @extends ServiceEntityRepository<CustomerDeletionRequest>
 */
class CustomerDeletionRequestRepository extends ServiceEntityRepository implements CustomerDeletionRequestRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerDeletionRequest::class);
    }

    public function findActiveForCustomer(CustomerInterface $customer): ?CustomerDeletionRequestInterface
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.customer = :customer')
            ->andWhere('r.cancelledAt IS NULL')
            ->andWhere('r.completedAt IS NULL')
            ->setParameter('customer', $customer)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findPendingById(int $id): ?CustomerDeletionRequestInterface
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.id = :id')
            ->andWhere('r.cancelledAt IS NULL')
            ->andWhere('r.completedAt IS NULL')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findDue(\DateTimeImmutable $now): array
    {
        /** @var list<CustomerDeletionRequest> $result */
        $result = $this->createQueryBuilder('r')
            ->andWhere('r.scheduledFor <= :now')
            ->andWhere('r.cancelledAt IS NULL')
            ->andWhere('r.completedAt IS NULL')
            ->setParameter('now', $now)
            ->orderBy('r.scheduledFor', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    public function findPendingForAdmin(): array
    {
        /** @var list<CustomerDeletionRequest> $result */
        $result = $this->createQueryBuilder('r')
            ->andWhere('r.cancelledAt IS NULL')
            ->andWhere('r.completedAt IS NULL')
            ->orderBy('r.scheduledFor', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }
}
