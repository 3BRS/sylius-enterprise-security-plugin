<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordExpirationShopUserInterface;

interface PasswordExpirationCheckerInterface
{
    public function isShopUserPasswordExpired(PasswordExpirationShopUserInterface $user): bool;

    public function isAdminUserPasswordExpired(PasswordExpirationAdminUserInterface $user): bool;
}
