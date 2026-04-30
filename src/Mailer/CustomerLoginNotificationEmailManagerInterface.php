<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentInfo;

interface CustomerLoginNotificationEmailManagerInterface
{
    public function sendNewDeviceNotification(
        ShopUserInterface $user,
        \DateTimeImmutable $loggedInAt,
        ?string $ipAddress,
        ?string $country,
        ?string $city,
        UserAgentInfo $userAgent,
    ): void;
}
