<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLinkInterface;

interface AdminUserSocialAccountLinkRepositoryInterface
{
    public function findByProviderAndProviderUserId(string $provider, string $providerUserId): ?AdminUserSocialAccountLinkInterface;

    public function findOneByAdminUserAndProvider(AdminUserInterface $user, string $provider): ?AdminUserSocialAccountLinkInterface;

    /**
     * @return list<AdminUserSocialAccountLinkInterface>
     */
    public function findAllByAdminUser(AdminUserInterface $user): array;

    public function countByAdminUser(AdminUserInterface $user): int;
}
