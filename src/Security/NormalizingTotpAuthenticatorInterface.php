<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Security;

use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

interface NormalizingTotpAuthenticatorInterface extends TotpAuthenticatorInterface
{
}
