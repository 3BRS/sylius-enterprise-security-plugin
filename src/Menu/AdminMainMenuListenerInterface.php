<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

interface AdminMainMenuListenerInterface
{
    public function addTwoFactorItem(MenuBuilderEvent $event): void;

    public function addSocialAccountsItem(MenuBuilderEvent $event): void;

    public function addPasskeyItem(MenuBuilderEvent $event): void;

    public function addLockedCustomersItem(MenuBuilderEvent $event): void;

    public function addLockedAdminsItem(MenuBuilderEvent $event): void;

    public function addSessionsItem(MenuBuilderEvent $event): void;

    public function addSecuritySettingsItem(MenuBuilderEvent $event): void;

    public function addAccountDeletionsItem(MenuBuilderEvent $event): void;

    public function addIpWhitelistItem(MenuBuilderEvent $event): void;
}
