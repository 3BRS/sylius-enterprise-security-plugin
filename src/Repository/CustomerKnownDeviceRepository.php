<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerKnownDevice;

/**
 * @extends ServiceEntityRepository<CustomerKnownDevice>
 */
class CustomerKnownDeviceRepository extends ServiceEntityRepository implements CustomerKnownDeviceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerKnownDevice::class);
    }

    public function existsForShopUser(ShopUserInterface $user, string $fingerprint): bool
    {
        $count = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.shopUser = :user')
            ->andWhere('d.fingerprint = :fingerprint')
            ->setParameter('user', $user)
            ->setParameter('fingerprint', $fingerprint)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }
}
