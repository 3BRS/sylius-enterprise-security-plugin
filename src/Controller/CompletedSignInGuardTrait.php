<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Replaces the bundle's full sign-in check for linking a social account, registering a passkey and
 * managing the second factor: a sign-in restored from a remember-me cookie may do them, a sign-in that
 * waits for its two-factor code may not.
 */
trait CompletedSignInGuardTrait
{
    protected function isFullSignIn(?TokenInterface $token): bool
    {
        return $token?->getUser() !== null && !$token instanceof TwoFactorTokenInterface;
    }
}
