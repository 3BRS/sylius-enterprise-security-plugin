<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerLoginPreferenceRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PasswordLoginControlExtension extends AbstractExtension implements PasswordLoginControlExtensionInterface
{
    public function __construct(
        protected FeatureToggleInterface $featureToggle,
        protected CustomerLoginPreferenceRepositoryInterface $customerPreferenceRepository,
        protected LastAuthMethodGuardInterface $guard,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('three_brs_customer_password_login_status', $this->customerStatus(...)),
        ];
    }

    public function customerStatus(ShopUserInterface $shopUser): array
    {
        return [
            'enabled' => $this->featureToggle->isEnabled('password_login_control', SettingsScope::CUSTOMER),
            'allowed' => $this->customerPreferenceRepository->isPasswordLoginAllowedForUser($shopUser),
            'can_disable' => $this->guard->canDisablePasswordLoginForShopUser($shopUser),
        ];
    }
}
