<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordLoginControl\PasswordLoginPreferenceInterface;

interface CustomerLoginPreferenceInterface extends PasswordLoginPreferenceInterface
{
    public function getShopUser(): ShopUserInterface;

    public function setShopUser(ShopUserInterface $shopUser): void;
}
