<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

class AdminMainMenuListener implements AdminMainMenuListenerInterface
{
    public function __construct(
        protected FeatureToggleInterface $features,
    ) {
    }

    public function addTwoFactorItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isTwoFactorActive(SettingsScope::ADMIN)) {
            return;
        }

        $configuration = $event->getMenu()->getChild('configuration');

        if ($configuration === null) {
            return;
        }

        $configuration
            ->addChild('two_factor_authentication', ['route' => 'three_brs_admin_two_factor_setup'])
            ->setLabel('three_brs.ui.two_factor.menu_item')
            ->setLabelAttribute('icon', 'tabler:shield-lock')
        ;
    }

    public function addSocialAccountsItem(MenuBuilderEvent $event): void
    {
        if (
            !$this->features->isEnabled('oauth.google', SettingsScope::ADMIN) &&
            !$this->features->isEnabled('oauth.apple', SettingsScope::ADMIN)
        ) {
            return;
        }

        $configuration = $event->getMenu()->getChild('configuration');

        if ($configuration === null) {
            return;
        }

        $configuration
            ->addChild('social_accounts', ['route' => 'three_brs_admin_social_accounts'])
            ->setLabel('three_brs.ui.social_login.menu_item')
            ->setLabelAttribute('icon', 'tabler:user-circle')
        ;
    }

    public function addPasskeyItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('passkey', SettingsScope::ADMIN)) {
            return;
        }

        $configuration = $event->getMenu()->getChild('configuration');

        if ($configuration === null) {
            return;
        }

        $configuration
            ->addChild('passkey', ['route' => 'three_brs_admin_passkey_index'])
            ->setLabel('three_brs.ui.passkey.menu_item')
            ->setLabelAttribute('icon', 'tabler:fingerprint')
        ;
    }

    public function addLockedCustomersItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('account_lockout', SettingsScope::CUSTOMER)) {
            return;
        }

        $configuration = $event->getMenu()->getChild('configuration');

        if ($configuration === null) {
            return;
        }

        $configuration
            ->addChild('locked_customers', ['route' => 'three_brs_admin_locked_customers'])
            ->setLabel('three_brs.ui.lockout.customers_menu_item')
            ->setLabelAttribute('icon', 'tabler:user-off')
        ;
    }

    public function addLockedAdminsItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('account_lockout', SettingsScope::ADMIN)) {
            return;
        }

        $configuration = $event->getMenu()->getChild('configuration');

        if ($configuration === null) {
            return;
        }

        $configuration
            ->addChild('locked_admins', ['route' => 'three_brs_admin_locked_admins'])
            ->setLabel('three_brs.ui.lockout.admins_menu_item')
            ->setLabelAttribute('icon', 'tabler:lock-off')
        ;
    }

    public function addSessionsItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('session_management', SettingsScope::ADMIN)) {
            return;
        }

        $configuration = $event->getMenu()->getChild('configuration');

        if ($configuration === null) {
            return;
        }

        $configuration
            ->addChild('three_brs_sessions', ['route' => 'three_brs_admin_sessions'])
            ->setLabel('three_brs.ui.session.menu_item')
            ->setLabelAttribute('icon', 'tabler:devices')
        ;
    }

    public function addSecuritySettingsItem(MenuBuilderEvent $event): void
    {
        $configuration = $event->getMenu()->getChild('configuration');

        if ($configuration === null) {
            return;
        }

        $configuration
            ->addChild('three_brs_security_settings', ['route' => 'three_brs_admin_security_settings_index'])
            ->setLabel('three_brs.ui.security_settings.menu_item')
            ->setLabelAttribute('icon', 'tabler:shield-check')
        ;
    }

    public function addAccountDeletionsItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('account_deletion', SettingsScope::CUSTOMER)) {
            return;
        }

        $configuration = $event->getMenu()->getChild('configuration');

        if ($configuration === null) {
            return;
        }

        $configuration
            ->addChild('three_brs_account_deletions', ['route' => 'three_brs_admin_account_deletions'])
            ->setLabel('three_brs.ui.account_deletion.admin_title')
            ->setLabelAttribute('icon', 'tabler:user-x')
        ;
    }

    public function addIpWhitelistItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('ip_whitelist', SettingsScope::GLOBAL)) {
            return;
        }

        $configuration = $event->getMenu()->getChild('configuration');

        if ($configuration === null) {
            return;
        }

        $configuration
            ->addChild('three_brs_ip_whitelist', ['route' => 'three_brs_admin_ip_whitelist_admins'])
            ->setLabel('three_brs.ui.ip_whitelist.menu_item')
            ->setLabelAttribute('icon', 'tabler:shield-lock')
        ;
    }
}
