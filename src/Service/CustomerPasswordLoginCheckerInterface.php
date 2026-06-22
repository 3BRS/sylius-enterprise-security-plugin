<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerPasswordLoginCheckerInterface
{
    public function isPasswordLoginBlocked(ShopUserInterface $user): bool;
}
