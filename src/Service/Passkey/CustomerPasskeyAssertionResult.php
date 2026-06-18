<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\ShopUserInterface;

class CustomerPasskeyAssertionResult implements CustomerPasskeyAssertionResultInterface
{
    public function __construct(
        protected readonly ShopUserInterface $user,
    ) {
    }

    public function getUser(): ShopUserInterface
    {
        return $this->user;
    }
}
