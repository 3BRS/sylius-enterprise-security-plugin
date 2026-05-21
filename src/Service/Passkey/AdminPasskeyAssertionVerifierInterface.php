<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyAssertionVerifierInterface;

interface AdminPasskeyAssertionVerifierInterface extends PasskeyAssertionVerifierInterface
{
    public function verify(string $credentialResponseJson, string $host): AdminPasskeyAssertionResultInterface;
}
