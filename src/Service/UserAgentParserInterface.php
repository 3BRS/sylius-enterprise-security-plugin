<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

interface UserAgentParserInterface
{
    /**
     * Returns a human-readable device description (e.g. "Chrome on macOS") or
     * null when the user agent is missing or cannot be parsed.
     */
    public function describe(?string $userAgent): ?string;
}
