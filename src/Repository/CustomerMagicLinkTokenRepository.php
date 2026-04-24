<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkTokenInterface;

/**
 * @extends ServiceEntityRepository<CustomerMagicLinkToken>
 */
class CustomerMagicLinkTokenRepository extends ServiceEntityRepository implements CustomerMagicLinkTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerMagicLinkToken::class);
    }

    public function findOneByTokenHash(string $tokenHash): ?CustomerMagicLinkTokenInterface
    {
        return $this->createQueryBuilder('t')
            ->where('t.tokenHash = :hash')
            ->setParameter('hash', $tokenHash)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function countRecentForShopUser(ShopUserInterface $user, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.shopUser = :user')
            ->andWhere('t.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
