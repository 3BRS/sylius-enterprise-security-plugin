<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerLoginPreference;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerLoginPreferenceInterface;

/**
 * @extends ServiceEntityRepository<CustomerLoginPreference>
 */
class CustomerLoginPreferenceRepository extends ServiceEntityRepository implements CustomerLoginPreferenceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerLoginPreference::class);
    }

    public function isPasswordLoginAllowedForUser(UserInterface $user): bool
    {
        if (!$user instanceof ShopUserInterface) {
            return true;
        }

        $preference = $this->findOneByShopUser($user);

        return $preference === null || $preference->isPasswordLoginAllowed();
    }

    public function findOneByShopUser(ShopUserInterface $shopUser): ?CustomerLoginPreferenceInterface
    {
        return $this->createQueryBuilder('p')
            ->where('p.shopUser = :user')
            ->setParameter('user', $shopUser)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
