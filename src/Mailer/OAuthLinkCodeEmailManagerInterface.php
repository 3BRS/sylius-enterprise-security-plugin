<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

interface OAuthLinkCodeEmailManagerInterface
{
    public function sendLinkCode(string $email, string $code, int $expirationSeconds): void;
}
