<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

class AdminMainMenuListener implements AdminMainMenuListenerInterface
{
    public function __construct(
        protected bool $passkeyEnabled,
        protected bool $customerLockoutEnabled,
        protected bool $adminLockoutEnabled,
        protected bool $sessionManagementAdminEnabled,
    ) {
    }

    public function addTwoFactorItem(MenuBuilderEvent $event): void
    {
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
        if (!$this->passkeyEnabled) {
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
        if (!$this->customerLockoutEnabled) {
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
        if (!$this->adminLockoutEnabled) {
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
        if (!$this->sessionManagementAdminEnabled) {
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
}
