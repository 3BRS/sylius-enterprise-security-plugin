<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminUserMagicLinkEmailManagerInterface
{
    public function sendMagicLink(AdminUserInterface $user, string $plainToken, int $expirationSeconds): void;
}
