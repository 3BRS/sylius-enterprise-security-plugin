<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Security;

use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\LockableAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout\AdminUserLockoutManagerInterface;

class AdminUserLockoutChecker implements AdminUserLockoutCheckerInterface
{
    public function __construct(
        protected AdminUserLockoutManagerInterface $lockoutManager,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof LockableAdminUserInterface) {
            return;
        }

        if ($this->lockoutManager->isLocked($user)) {
            throw new LockedException('three_brs.lockout.account_locked');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Lockout state is fully resolved in checkPreAuth — by the time the password
        // verifier accepts the credentials, we already know the account is unlocked.
        // No post-auth check needed.
    }
}
