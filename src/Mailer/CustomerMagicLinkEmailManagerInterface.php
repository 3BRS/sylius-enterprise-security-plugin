<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Core\Model\ShopUserInterface;

interface CustomerMagicLinkEmailManagerInterface
{
    public function sendMagicLink(ShopUserInterface $user, string $plainToken, int $expirationSeconds): void;
}
