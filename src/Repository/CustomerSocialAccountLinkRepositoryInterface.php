<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLinkInterface;

interface CustomerSocialAccountLinkRepositoryInterface
{
    public function findByProviderAndProviderUserId(string $provider, string $providerUserId): ?CustomerSocialAccountLinkInterface;

    public function findOneByShopUserAndProvider(ShopUserInterface $user, string $provider): ?CustomerSocialAccountLinkInterface;

    /**
     * @return list<CustomerSocialAccountLinkInterface>
     */
    public function findAllByShopUser(ShopUserInterface $user): array;

    public function countByShopUser(ShopUserInterface $user): int;
}
