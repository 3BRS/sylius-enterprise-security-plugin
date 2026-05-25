<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredentialInterface;

interface AdminUserPasskeyCredentialRepositoryInterface extends PasskeyCredentialRepositoryInterface
{
    public function findOneByCredentialId(string $credentialId): ?AdminUserPasskeyCredentialInterface;

    public function findOneByIdAndAdminUser(int $id, AdminUserInterface $user): ?AdminUserPasskeyCredentialInterface;

    /** @return list<AdminUserPasskeyCredentialInterface> */
    public function findAllByAdminUser(AdminUserInterface $user): array;

    public function countByAdminUser(AdminUserInterface $user): int;
}
