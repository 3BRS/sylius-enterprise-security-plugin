<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

interface GeoIpLookupInterface
{
    public function lookup(?string $ipAddress): ?GeoIpResult;
}
