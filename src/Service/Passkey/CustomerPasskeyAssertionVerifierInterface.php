<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

interface CustomerPasskeyAssertionVerifierInterface
{
    public function verify(string $credentialResponseJson, string $host): PasskeyAssertionResultInterface;
}
