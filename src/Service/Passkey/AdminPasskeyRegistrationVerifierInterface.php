<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredentialInterface;

interface AdminPasskeyRegistrationVerifierInterface
{
    public function verifyAndCreate(
        AdminUserInterface $user,
        string $credentialResponseJson,
        string $label,
        string $host,
    ): AdminUserPasskeyCredentialInterface;
}
