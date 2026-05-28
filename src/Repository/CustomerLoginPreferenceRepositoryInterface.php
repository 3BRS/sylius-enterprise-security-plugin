<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordLoginControl\PasswordLoginPreferenceRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerLoginPreferenceInterface;

interface CustomerLoginPreferenceRepositoryInterface extends PasswordLoginPreferenceRepositoryInterface
{
    public function findOneByShopUser(ShopUserInterface $shopUser): ?CustomerLoginPreferenceInterface;
}
