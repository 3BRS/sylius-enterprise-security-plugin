<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\SessionLifetimeInterface;

/**
 * @extends ServiceEntityRepository<AdminUserSession>
 */
class AdminUserSessionRepository extends ServiceEntityRepository implements AdminUserSessionRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        protected SessionLifetimeInterface $sessionLifetime,
    ) {
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
            ->andWhere('s.lastActivityAt > :activeSince')
            ->setParameter('user', $user)
            ->setParameter('activeSince', $this->sessionLifetime->getActiveSince())
            ->orderBy('s.lastActivityAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    public function findUnrevokedForAdminUser(AdminUserInterface $user): array
    {
        /** @var list<AdminUserSession> $result */
        $result = $this->createQueryBuilder('s')
            ->where('s.adminUser = :user')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('user', $user)
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
