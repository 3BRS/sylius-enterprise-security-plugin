<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

class UserAgentInfo
{
    public function __construct(
        public readonly ?string $browser,
        public readonly ?string $operatingSystem,
        public readonly ?string $deviceType,
    ) {
    }
}
