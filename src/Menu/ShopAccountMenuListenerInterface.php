<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

interface ShopAccountMenuListenerInterface
{
    public function addTwoFactorItem(MenuBuilderEvent $event): void;
}
