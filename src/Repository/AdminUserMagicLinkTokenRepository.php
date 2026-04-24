<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkTokenInterface;

/**
 * @extends ServiceEntityRepository<AdminUserMagicLinkToken>
 */
class AdminUserMagicLinkTokenRepository extends ServiceEntityRepository implements AdminUserMagicLinkTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUserMagicLinkToken::class);
    }

    public function findOneByTokenHash(string $tokenHash): ?AdminUserMagicLinkTokenInterface
    {
        return $this->createQueryBuilder('t')
            ->where('t.tokenHash = :hash')
            ->setParameter('hash', $tokenHash)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function countRecentForAdminUser(AdminUserInterface $user, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.adminUser = :user')
            ->andWhere('t.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function deleteExpiredBefore(\DateTimeImmutable $threshold): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;
    }
}
