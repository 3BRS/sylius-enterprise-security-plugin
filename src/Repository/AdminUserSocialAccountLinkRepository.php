<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLinkInterface;

/**
 * @extends ServiceEntityRepository<AdminUserSocialAccountLink>
 */
class AdminUserSocialAccountLinkRepository extends ServiceEntityRepository implements AdminUserSocialAccountLinkRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUserSocialAccountLink::class);
    }

    public function findByProviderAndProviderUserId(string $provider, string $providerUserId): ?AdminUserSocialAccountLinkInterface
    {
        return $this->createQueryBuilder('l')
            ->where('l.provider = :provider')
            ->andWhere('l.providerUserId = :providerUserId')
            ->setParameter('provider', $provider)
            ->setParameter('providerUserId', $providerUserId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findOneByAdminUserAndProvider(AdminUserInterface $user, string $provider): ?AdminUserSocialAccountLinkInterface
    {
        return $this->createQueryBuilder('l')
            ->where('l.adminUser = :user')
            ->andWhere('l.provider = :provider')
            ->setParameter('user', $user)
            ->setParameter('provider', $provider)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findAllByAdminUser(AdminUserInterface $user): array
    {
        /** @var list<AdminUserSocialAccountLink> $result */
        $result = $this->createQueryBuilder('l')
            ->where('l.adminUser = :user')
            ->setParameter('user', $user)
            ->orderBy('l.linkedAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    public function countByAdminUser(AdminUserInterface $user): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.adminUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
