<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

class AdminMainMenuListener implements AdminMainMenuListenerInterface
{
    public function __construct(
        protected FeatureToggleInterface $features,
    ) {
    }

    public function addLockedCustomersItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('account_lockout', SettingsScope::CUSTOMER)) {
            return;
        }

        $customers = $event->getMenu()->getChild('customers');
        if ($customers === null) {
            return;
        }

        $customers
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

        $this->placeItemAfter($configuration, 'locked_admins', 'admin_users');
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

        $customers = $event->getMenu()->getChild('customers');
        if ($customers === null) {
            return;
        }

        $customers
            ->addChild('three_brs_account_deletions', ['route' => 'three_brs_admin_account_deletions'])
            ->setLabel('three_brs.ui.account_deletion.admin_title')
            ->setLabelAttribute('icon', 'tabler:user-x')
        ;
    }

    public function addIpWhitelistItem(MenuBuilderEvent $event): void
    {
        if (!$this->features->isEnabled('ip_whitelist', SettingsScope::ADMIN)) {
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

        // Place right under locked_admins when both are present; fall back to admin_users.
        $anchor = $configuration->getChild('locked_admins') !== null ? 'locked_admins' : 'admin_users';
        $this->placeItemAfter($configuration, 'three_brs_ip_whitelist', $anchor);
    }

    protected function placeItemAfter(ItemInterface $parent, string $newItem, string $afterItem): void
    {
        $names = array_keys($parent->getChildren());
        if (!in_array($newItem, $names, true) || !in_array($afterItem, $names, true)) {
            return;
        }

        $names = array_values(array_filter($names, static fn (string $name): bool => $name !== $newItem));
        $anchorIndex = (int) array_search($afterItem, $names, true);
        array_splice($names, $anchorIndex + 1, 0, $newItem);

        $parent->reorderChildren($names);
    }
}
