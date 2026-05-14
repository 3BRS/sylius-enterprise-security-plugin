<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredentialInterface;

interface CustomerPasskeyRegistrationVerifierInterface
{
    public function verifyAndCreate(
        ShopUserInterface $user,
        string $credentialResponseJson,
        string $label,
        string $host,
    ): CustomerPasskeyCredentialInterface;
}
