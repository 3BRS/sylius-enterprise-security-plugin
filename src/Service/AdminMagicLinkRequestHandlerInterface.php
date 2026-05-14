<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

interface AdminMagicLinkRequestHandlerInterface
{
    public function request(string $email): void;
}
