<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordLoginControl\AbstractPasswordLoginCheckListener;
use ThreeBRS\EnterpriseSecurityBundle\PasswordLoginControl\PasswordLoginPreferenceRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

class AdminUserPasswordLoginCheckListener extends AbstractPasswordLoginCheckListener implements AdminUserPasswordLoginCheckListenerInterface
{
    public function __construct(
        PasswordLoginPreferenceRepositoryInterface $preferenceRepository,
        protected FeatureToggleInterface $featureToggle,
    ) {
        parent::__construct($preferenceRepository);
    }

    protected function isFeatureEnabled(): bool
    {
        return $this->featureToggle->isEnabled('password_login_control', SettingsScope::ADMIN);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface;
    }

    protected function getErrorMessageKey(): string
    {
        return 'three_brs.password_login_control.disabled_for_user';
    }
}
