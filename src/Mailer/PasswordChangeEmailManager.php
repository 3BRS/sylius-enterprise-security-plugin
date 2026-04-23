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
        private bool $customerEnabled,
        private bool $adminEnabled,
    ) {
    }

    public function sendCustomerPasswordChangedEmail(ShopUserInterface $user, ?Request $request, bool $initiatedByUser): void
    {
        if (!$this->customerEnabled) {
            return;
        }

        $email = $user->getEmail();
        if ($email === null) {
            return;
        }

        $this->emailSender->send(
            code: Emails::CUSTOMER_PASSWORD_CHANGED,
            recipients: [$email],
            data: $this->buildEmailData($request, $initiatedByUser, 'sylius_shop_account_change_password'),
        );
    }

    public function sendAdminPasswordChangedEmail(AdminUserInterface $user, ?Request $request, bool $initiatedByUser): void
    {
        if (!$this->adminEnabled) {
            return;
        }

        $email = $user->getEmail();
        if ($email === null) {
            return;
        }

        $this->emailSender->send(
            code: Emails::ADMIN_PASSWORD_CHANGED,
            recipients: [$email],
            data: $this->buildEmailData($request, $initiatedByUser, 'sylius_admin_login'),
        );
    }

    /** @return array<string, mixed> */
    private function buildEmailData(?Request $request, bool $initiatedByUser, string $secureRoute): array
    {
        return [
            'timestamp' => new \DateTimeImmutable(),
            'ipAddress' => $request?->getClientIp(),
            'userAgent' => $request?->headers->get('User-Agent'),
            'initiatedByUser' => $initiatedByUser,
            'secureAccountUrl' => $initiatedByUser ? null : $this->router->generate($secureRoute, [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
