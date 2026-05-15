<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

interface SessionFingerprintGeneratorInterface
{
    public function generate(?string $userAgent, ?string $ipAddress): string;
}
