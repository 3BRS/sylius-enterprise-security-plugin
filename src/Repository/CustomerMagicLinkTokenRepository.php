<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
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
}
