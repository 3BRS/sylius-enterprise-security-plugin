<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Webauthn\PublicKeyCredentialRequestOptions;

interface CustomerPasskeyAssertionOptionsBuilderInterface
{
    public function build(): PublicKeyCredentialRequestOptions;
}
