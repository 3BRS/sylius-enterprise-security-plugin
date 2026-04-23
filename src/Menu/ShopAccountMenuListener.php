<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

class ShopAccountMenuListener implements ShopAccountMenuListenerInterface
{
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
}
