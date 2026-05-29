<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredential;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredentialInterface;

/**
 * @extends ServiceEntityRepository<AdminUserPasskeyCredential>
 */
class AdminUserPasskeyCredentialRepository extends ServiceEntityRepository implements AdminUserPasskeyCredentialRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUserPasskeyCredential::class);
    }

    public function findOneByCredentialId(string $credentialId): ?AdminUserPasskeyCredentialInterface
    {
        return $this->createQueryBuilder('c')
            ->where('c.credentialId = :credentialId')
            ->setParameter('credentialId', $credentialId, Types::BINARY)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findOneByIdAndAdminUser(int $id, AdminUserInterface $user): ?AdminUserPasskeyCredentialInterface
    {
        return $this->createQueryBuilder('c')
            ->where('c.id = :id')
            ->andWhere('c.adminUser = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findAllByAdminUser(AdminUserInterface $user): array
    {
        /** @var list<AdminUserPasskeyCredential> $result */
        $result = $this->createQueryBuilder('c')
            ->where('c.adminUser = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    public function countByAdminUser(AdminUserInterface $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.adminUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
