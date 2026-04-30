<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

class ShopAccountMenuListener implements ShopAccountMenuListenerInterface
{
    public function __construct(
        protected bool $passkeyEnabled,
        protected bool $sessionManagementEnabled,
    ) {
    }

    public function addTwoFactorItem(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $menu
            ->addChild('two_factor_authentication', ['route' => 'three_brs_shop_two_factor_setup'])
            ->setLabel('three_brs.ui.two_factor.menu_item')
            ->setLabelAttribute('icon', 'tabler:shield-lock')
        ;
    }

    public function addSocialAccountsItem(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $menu
            ->addChild('social_accounts', ['route' => 'three_brs_shop_social_accounts'])
            ->setLabel('three_brs.ui.social_login.menu_item')
            ->setLabelAttribute('icon', 'tabler:user-circle')
        ;
    }

    public function addPasskeyItem(MenuBuilderEvent $event): void
    {
        if (!$this->passkeyEnabled) {
            return;
        }

        $menu = $event->getMenu();

        $menu
            ->addChild('passkey', ['route' => 'three_brs_shop_passkey_index'])
            ->setLabel('three_brs.ui.passkey.menu_item')
            ->setLabelAttribute('icon', 'tabler:fingerprint')
        ;
    }

    public function addSessionsItem(MenuBuilderEvent $event): void
    {
        if (!$this->sessionManagementEnabled) {
            return;
        }

        $menu = $event->getMenu();

        $menu
            ->addChild('sessions', ['route' => 'three_brs_shop_sessions'])
            ->setLabel('three_brs.ui.session.menu_item')
            ->setLabelAttribute('icon', 'tabler:devices')
        ;
    }
}
