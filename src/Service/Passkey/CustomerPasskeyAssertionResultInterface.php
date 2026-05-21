<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyAssertionResultInterface;

interface CustomerPasskeyAssertionResultInterface extends PasskeyAssertionResultInterface
{
    public function getUser(): ShopUserInterface;
}
