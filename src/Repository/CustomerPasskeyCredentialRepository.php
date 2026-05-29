<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredential;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredentialInterface;

/**
 * @extends ServiceEntityRepository<CustomerPasskeyCredential>
 */
class CustomerPasskeyCredentialRepository extends ServiceEntityRepository implements CustomerPasskeyCredentialRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerPasskeyCredential::class);
    }

    public function findOneByCredentialId(string $credentialId): ?CustomerPasskeyCredentialInterface
    {
        return $this->createQueryBuilder('c')
            ->where('c.credentialId = :credentialId')
            ->setParameter('credentialId', $credentialId, Types::BINARY)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findOneByIdAndShopUser(int $id, ShopUserInterface $user): ?CustomerPasskeyCredentialInterface
    {
        return $this->createQueryBuilder('c')
            ->where('c.id = :id')
            ->andWhere('c.shopUser = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findAllByShopUser(ShopUserInterface $user): array
    {
        /** @var list<CustomerPasskeyCredential> $result */
        $result = $this->createQueryBuilder('c')
            ->where('c.shopUser = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    public function countByShopUser(ShopUserInterface $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.shopUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
