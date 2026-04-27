<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserInterface;

class LockedAdminUserRepository implements LockedAdminUserRepositoryInterface
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected string $adminUserClass,
    ) {
    }

    public function findAllLocked(): array
    {
        /** @var list<AdminUserInterface> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from($this->adminUserClass, 'u')
            ->where('u.lockedAt IS NOT NULL')
            ->orderBy('u.lockedAt', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<AdminUserInterface&LockableAdminUserInterface> $filtered */
        $filtered = array_values(array_filter(
            $result,
            static fn ($u): bool => $u instanceof LockableAdminUserInterface,
        ));

        return $filtered;
    }
}
