<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSessionInterface;

/**
 * @extends ServiceEntityRepository<AdminUserSession>
 */
class AdminUserSessionRepository extends ServiceEntityRepository implements AdminUserSessionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUserSession::class);
    }

    public function findOneBySessionId(string $sessionId): ?AdminUserSessionInterface
    {
        return $this->createQueryBuilder('s')
            ->where('s.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findActiveForAdminUser(AdminUserInterface $user): array
    {
        /** @var list<AdminUserSession> $result */
        $result = $this->createQueryBuilder('s')
            ->where('s.adminUser = :user')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('s.lastActivityAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    public function findActiveByIdForAdminUser(int $id, AdminUserInterface $user): ?AdminUserSessionInterface
    {
        return $this->createQueryBuilder('s')
            ->where('s.id = :id')
            ->andWhere('s.adminUser = :user')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
