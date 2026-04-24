<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkTokenInterface;

interface AdminUserMagicLinkTokenRepositoryInterface
{
    public function findOneByTokenHash(string $tokenHash): ?AdminUserMagicLinkTokenInterface;

    public function countRecentForAdminUser(AdminUserInterface $user, \DateTimeImmutable $since): int;
}
