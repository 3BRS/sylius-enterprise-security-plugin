<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CustomerMagicLinkEmailManager implements CustomerMagicLinkEmailManagerInterface
{
    public function __construct(
        private SenderInterface $emailSender,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function sendMagicLink(ShopUserInterface $user, string $plainToken, int $expirationSeconds): void
    {
        $email = $user->getEmail();
        if ($email === null) {
            return;
        }

        $magicLinkUrl = $this->router->generate(
            'three_brs_shop_magic_link_verify',
            ['token' => $plainToken],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->emailSender->send(
            code: Emails::CUSTOMER_MAGIC_LINK,
            recipients: [$email],
            data: [
                'user' => $user,
                'magicLinkUrl' => $magicLinkUrl,
                'expirationMinutes' => (int) ceil($expirationSeconds / 60),
            ],
        );
    }
}
