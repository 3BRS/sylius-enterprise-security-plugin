<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;

interface PasskeyValidatorFactoryInterface
{
    public function createAttestationValidator(): AuthenticatorAttestationResponseValidator;

    public function createAssertionValidator(): AuthenticatorAssertionResponseValidator;
}
