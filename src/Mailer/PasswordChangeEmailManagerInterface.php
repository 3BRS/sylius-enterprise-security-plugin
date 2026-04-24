<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;

interface PasswordChangeEmailManagerInterface
{
    public function sendPasswordChangedEmail(
        ShopUserInterface|AdminUserInterface $user,
        ?Request $request,
        bool $initiatedByUser,
    ): void;
}
