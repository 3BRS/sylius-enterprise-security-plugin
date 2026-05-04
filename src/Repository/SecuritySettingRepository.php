<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySetting;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySettingInterface;

/**
 * @extends ServiceEntityRepository<SecuritySetting>
 */
class SecuritySettingRepository extends ServiceEntityRepository implements SecuritySettingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecuritySetting::class);
    }

    public function findOneByPathAndScope(string $path, string $scope): ?SecuritySettingInterface
    {
        return $this->findOneBy(['path' => $path, 'scope' => $scope]);
    }

    /**
     * @return list<SecuritySettingInterface>
     */
    public function findAllSettings(): array
    {
        return $this->findAll();
    }

    public function deleteAll(): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->getQuery()
            ->execute()
        ;
    }
}
