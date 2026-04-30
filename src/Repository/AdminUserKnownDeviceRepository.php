<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserKnownDevice;

/**
 * @extends ServiceEntityRepository<AdminUserKnownDevice>
 */
class AdminUserKnownDeviceRepository extends ServiceEntityRepository implements AdminUserKnownDeviceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUserKnownDevice::class);
    }

    public function existsForAdminUser(AdminUserInterface $user, string $fingerprint): bool
    {
        $count = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.adminUser = :user')
            ->andWhere('d.fingerprint = :fingerprint')
            ->setParameter('user', $user)
            ->setParameter('fingerprint', $fingerprint)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }
}
