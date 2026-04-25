<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\ShopUserInterface;
use Webauthn\PublicKeyCredentialCreationOptions;

interface CustomerPasskeyRegistrationOptionsBuilderInterface
{
    public function build(ShopUserInterface $user): PublicKeyCredentialCreationOptions;
}
