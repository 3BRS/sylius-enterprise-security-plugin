<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpWhitelist;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpWhitelistInterface;

/**
 * @extends ServiceEntityRepository<AdminUserIpWhitelist>
 */
class AdminUserIpWhitelistRepository extends ServiceEntityRepository implements AdminUserIpWhitelistRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUserIpWhitelist::class);
    }

    public function findOneByAdminUser(AdminUserInterface $user): ?AdminUserIpWhitelistInterface
    {
        return $this->createQueryBuilder('w')
            ->where('w.adminUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findAllEnabled(): array
    {
        /** @var list<AdminUserIpWhitelist> $result */
        $result = $this->createQueryBuilder('w')
            ->where('w.enabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }
}
