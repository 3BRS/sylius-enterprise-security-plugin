<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

interface ShopAccountMenuListenerInterface
{
    public function addTwoFactorItem(MenuBuilderEvent $event): void;

    public function addSocialAccountsItem(MenuBuilderEvent $event): void;

    public function addPasskeyItem(MenuBuilderEvent $event): void;

    public function addSessionsItem(MenuBuilderEvent $event): void;

    public function addAccountDeletionItem(MenuBuilderEvent $event): void;

    public function swapChangePasswordItem(MenuBuilderEvent $event): void;
}
