<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentInfo;

interface AdminUserLoginNotificationEmailManagerInterface
{
    public function sendNewDeviceNotification(
        AdminUserInterface $user,
        \DateTimeImmutable $loggedInAt,
        ?string $ipAddress,
        ?string $country,
        ?string $city,
        UserAgentInfo $userAgent,
    ): void;
}
