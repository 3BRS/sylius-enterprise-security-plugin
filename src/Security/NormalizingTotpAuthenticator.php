<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Security;

use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

/**
 * Accepts a TOTP code written as `123-456`, the form `totp_input.js` shows it in; scheb removes
 * spaces only.
 */
class NormalizingTotpAuthenticator implements NormalizingTotpAuthenticatorInterface
{
    public function __construct(
        protected TotpAuthenticatorInterface $inner,
    ) {
    }

    public function checkCode(TwoFactorInterface $user, string $code): bool
    {
        return $this->inner->checkCode($user, str_replace('-', '', $code));
    }

    public function getQRContent(TwoFactorInterface $user): string
    {
        return $this->inner->getQRContent($user);
    }

    public function generateSecret(): string
    {
        return $this->inner->generateSecret();
    }
}
