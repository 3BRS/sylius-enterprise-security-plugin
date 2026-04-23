<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLinkInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLinkInterface;

interface SocialAccountLinksExtensionInterface
{
    /**
     * @return list<CustomerSocialAccountLinkInterface>
     */
    public function getCustomerLinks(ShopUserInterface $user): array;

    /**
     * @return list<AdminUserSocialAccountLinkInterface>
     */
    public function getAdminLinks(AdminUserInterface $user): array;
}
