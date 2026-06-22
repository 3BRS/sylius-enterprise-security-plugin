<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Mailer\Sender\SenderInterface;

class OAuthLinkCodeEmailManager implements OAuthLinkCodeEmailManagerInterface
{
    public function __construct(
        protected SenderInterface $emailSender,
    ) {
    }

    public function sendLinkCode(string $email, string $code, int $expirationSeconds): void
    {
        $this->emailSender->send(
            code: Emails::OAUTH_LINK_CODE,
            recipients: [$email],
            data: [
                'code' => $code,
                'expirationMinutes' => (int) ceil($expirationSeconds / 60),
            ],
        );
    }
}
