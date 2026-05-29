<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordLoginControl\AbstractPasswordLoginCheckListener;
use ThreeBRS\EnterpriseSecurityBundle\PasswordLoginControl\PasswordLoginPreferenceRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

class CustomerPasswordLoginCheckListener extends AbstractPasswordLoginCheckListener implements CustomerPasswordLoginCheckListenerInterface
{
    public function __construct(
        PasswordLoginPreferenceRepositoryInterface $preferenceRepository,
        protected FeatureToggleInterface $featureToggle,
    ) {
        parent::__construct($preferenceRepository);
    }

    protected function isFeatureEnabled(): bool
    {
        return $this->featureToggle->isEnabled('password_login_control', SettingsScope::CUSTOMER);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
    }

    protected function getErrorMessageKey(): string
    {
        return 'three_brs.password_login_control.disabled_for_user';
    }
}
