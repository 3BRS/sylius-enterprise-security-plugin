<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerPasskeyAssertionResultInterface
{
    public function getUser(): ShopUserInterface;

    public function isUserVerified(): bool;
}
