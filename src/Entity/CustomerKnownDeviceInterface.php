<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\KnownDeviceRecordInterface;

interface CustomerKnownDeviceInterface extends KnownDeviceRecordInterface
{
    public function getShopUser(): ShopUserInterface;

    public function setShopUser(ShopUserInterface $shopUser): void;
}
