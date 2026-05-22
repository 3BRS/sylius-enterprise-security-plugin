<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpBlacklist;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpBlacklistInterface;

/**
 * @extends ServiceEntityRepository<AdminUserIpBlacklist>
 */
class AdminUserIpBlacklistRepository extends ServiceEntityRepository implements AdminUserIpBlacklistRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUserIpBlacklist::class);
    }

    public function findOneByUser(UserInterface $user): ?AdminUserIpBlacklistInterface
    {
        if (!$user instanceof AdminUserInterface) {
            return null;
        }

        return $this->findOneByAdminUser($user);
    }

    public function findOneByAdminUser(AdminUserInterface $user): ?AdminUserIpBlacklistInterface
    {
        return $this->createQueryBuilder('b')
            ->where('b.adminUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findAllEnabled(): array
    {
        /** @var list<AdminUserIpBlacklist> $result */
        $result = $this->createQueryBuilder('b')
            ->where('b.enabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }
}
