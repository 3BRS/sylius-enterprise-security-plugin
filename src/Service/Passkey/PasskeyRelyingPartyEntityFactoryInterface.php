<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Webauthn\PublicKeyCredentialRpEntity;

interface PasskeyRelyingPartyEntityFactoryInterface
{
    public function create(): PublicKeyCredentialRpEntity;

    public function getRpId(): string;
}
