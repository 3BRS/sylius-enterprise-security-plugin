<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLinkInterface;

/**
 * @extends ServiceEntityRepository<CustomerSocialAccountLink>
 */
class CustomerSocialAccountLinkRepository extends ServiceEntityRepository implements CustomerSocialAccountLinkRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerSocialAccountLink::class);
    }

    public function findByProviderAndProviderUserId(string $provider, string $providerUserId): ?CustomerSocialAccountLinkInterface
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

    public function findOneByShopUserAndProvider(ShopUserInterface $user, string $provider): ?CustomerSocialAccountLinkInterface
    {
        return $this->createQueryBuilder('l')
            ->where('l.shopUser = :user')
            ->andWhere('l.provider = :provider')
            ->setParameter('user', $user)
            ->setParameter('provider', $provider)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findAllByShopUser(ShopUserInterface $user): array
    {
        /** @var list<CustomerSocialAccountLink> $result */
        $result = $this->createQueryBuilder('l')
            ->where('l.shopUser = :user')
            ->setParameter('user', $user)
            ->orderBy('l.linkedAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    public function countByShopUser(ShopUserInterface $user): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.shopUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
