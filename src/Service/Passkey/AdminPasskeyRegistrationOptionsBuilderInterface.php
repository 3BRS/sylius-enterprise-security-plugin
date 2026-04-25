<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\AdminUserInterface;
use Webauthn\PublicKeyCredentialCreationOptions;

interface AdminPasskeyRegistrationOptionsBuilderInterface
{
    public function build(AdminUserInterface $user): PublicKeyCredentialCreationOptions;
}
