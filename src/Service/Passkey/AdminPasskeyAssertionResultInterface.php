<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminPasskeyAssertionResultInterface
{
    public function getUser(): AdminUserInterface;

    public function isUserVerified(): bool;
}
