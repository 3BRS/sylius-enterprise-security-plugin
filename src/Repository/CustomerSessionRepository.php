<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;

/**
 * @extends ServiceEntityRepository<CustomerSession>
 */
class CustomerSessionRepository extends ServiceEntityRepository implements CustomerSessionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerSession::class);
    }

    public function findOneBySessionId(string $sessionId): ?CustomerSessionInterface
    {
        return $this->createQueryBuilder('s')
            ->where('s.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findActiveForShopUser(ShopUserInterface $user): array
    {
        /** @var list<CustomerSession> $result */
        $result = $this->createQueryBuilder('s')
            ->where('s.shopUser = :user')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('s.lastActivityAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    public function findActiveByIdForShopUser(int $id, ShopUserInterface $user): ?CustomerSessionInterface
    {
        return $this->createQueryBuilder('s')
            ->where('s.id = :id')
            ->andWhere('s.shopUser = :user')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
