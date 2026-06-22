<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerLoginPreferenceRepositoryInterface;

class CustomerPasswordLoginChecker implements CustomerPasswordLoginCheckerInterface
{
    public function __construct(
        protected FeatureToggleInterface $featureToggle,
        protected CustomerLoginPreferenceRepositoryInterface $preferenceRepository,
    ) {
    }

    public function isPasswordLoginBlocked(ShopUserInterface $user): bool
    {
        // Mirrors AbstractPasswordLoginCheckListener: a per-user preference only
        // blocks password login while the feature is enabled for the customer group.
        // Keeping this in sync guarantees the account UI never hides a password action
        // that the firewall would actually still allow (and vice versa).
        return $this->featureToggle->isEnabled('password_login_control', SettingsScope::CUSTOMER) &&
            !$this->preferenceRepository->isPasswordLoginAllowedForUser($user);
    }
}
