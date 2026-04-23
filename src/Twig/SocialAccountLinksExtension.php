<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SocialAccountLinksExtension extends AbstractExtension implements SocialAccountLinksExtensionInterface
{
    public function __construct(
        private CustomerSocialAccountLinkRepositoryInterface $customerLinkRepository,
        private AdminUserSocialAccountLinkRepositoryInterface $adminLinkRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('three_brs_customer_social_links', $this->getCustomerLinks(...)),
            new TwigFunction('three_brs_admin_social_links', $this->getAdminLinks(...)),
        ];
    }

    public function getCustomerLinks(ShopUserInterface $user): array
    {
        return $this->customerLinkRepository->findAllByShopUser($user);
    }

    public function getAdminLinks(AdminUserInterface $user): array
    {
        return $this->adminLinkRepository->findAllByAdminUser($user);
    }
}
