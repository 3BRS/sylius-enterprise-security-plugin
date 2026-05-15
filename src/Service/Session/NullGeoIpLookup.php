<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

class NullGeoIpLookup implements GeoIpLookupInterface
{
    public function lookup(?string $ipAddress): ?GeoIpResult
    {
        return null;
    }
}
