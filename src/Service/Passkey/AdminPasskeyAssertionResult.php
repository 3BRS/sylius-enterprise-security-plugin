<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\AdminUserInterface;

class AdminPasskeyAssertionResult implements AdminPasskeyAssertionResultInterface
{
    public function __construct(
        protected readonly AdminUserInterface $user,
    ) {
    }

    public function getUser(): AdminUserInterface
    {
        return $this->user;
    }
}
