<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyAssertionResultInterface;

interface AdminPasskeyAssertionResultInterface extends PasskeyAssertionResultInterface
{
    public function getUser(): AdminUserInterface;
}
