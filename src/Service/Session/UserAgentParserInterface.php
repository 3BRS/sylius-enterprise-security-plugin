<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

interface UserAgentParserInterface
{
    public function parse(?string $userAgent): UserAgentInfo;
}
