<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Webauthn\PublicKeyCredentialRequestOptions;

interface AdminPasskeyAssertionOptionsBuilderInterface
{
    public function build(): PublicKeyCredentialRequestOptions;
}
