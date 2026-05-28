<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordLoginControlAdminUserInterface;

/**
 * Admins store the password-login flag as a column on the admin user itself
 * (PasswordLoginControlAdminUserTrait), not in a separate record — so this adapter
 * lets the bundle's AbstractPasswordLoginCheckListener read it through the standard
 * repository contract.
 */
class AdminUserPasswordLoginPreferenceProvider implements AdminUserPasswordLoginPreferenceProviderInterface
{
    public function isPasswordLoginAllowedForUser(UserInterface $user): bool
    {
        return !$user instanceof PasswordLoginControlAdminUserInterface || $user->isPasswordLoginAllowed();
    }
}
