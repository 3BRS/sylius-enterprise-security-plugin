<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

class GeoIpResult
{
    public function __construct(
        public readonly ?string $countryCode,
        public readonly ?string $city,
    ) {
    }
}
