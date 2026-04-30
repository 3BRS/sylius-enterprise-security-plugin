<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordChangeEmailManager implements PasswordChangeEmailManagerInterface
{
    public function __construct(
        private SenderInterface $emailSender,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function sendPasswordChangedEmail(
        ShopUserInterface|AdminUserInterface $user,
        ?Request $request,
        bool $initiatedByUser,
    ): void {
        $email = $user->getEmail();
        if ($email === null) {
            return;
        }

        $secureRoute = $user instanceof AdminUserInterface
            ? 'sylius_admin_request_password_reset'
            : 'sylius_shop_request_password_reset_token';

        $this->emailSender->send(
            code: Emails::PASSWORD_CHANGED,
            recipients: [$email],
            data: [
                'timestamp' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                'ipAddress' => $request?->getClientIp(),
                'initiatedByUser' => $initiatedByUser,
                'secureAccountUrl' => $initiatedByUser ? null : $this->router->generate($secureRoute, [], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        );
    }
}
