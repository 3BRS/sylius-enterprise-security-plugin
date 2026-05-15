<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use DateTimeImmutable;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentInfo;

class CustomerLoginNotificationEmailManager implements CustomerLoginNotificationEmailManagerInterface
{
    public function __construct(
        protected SenderInterface $emailSender,
    ) {
    }

    public function sendNewDeviceNotification(
        ShopUserInterface $user,
        DateTimeImmutable $loggedInAt,
        ?string $ipAddress,
        ?string $country,
        ?string $city,
        UserAgentInfo $userAgent,
    ): void {
        $email = $user->getEmail();
        if ($email === null) {
            return;
        }

        $this->emailSender->send(
            code: Emails::LOGIN_NOTIFICATION,
            recipients: [$email],
            data: [
                'user' => $user,
                'loggedInAt' => $loggedInAt,
                'ipAddress' => $ipAddress,
                'country' => $country,
                'city' => $city,
                'browser' => $userAgent->browser,
                'operatingSystem' => $userAgent->operatingSystem,
            ],
        );
    }
}
