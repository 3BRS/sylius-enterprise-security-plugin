<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\GeoIpLookupInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\GeoIpResult;

/**
 * Dev-only fake. Maps a handful of well-known IP prefixes (Docker bridge,
 * RFC5737 documentation ranges) to deterministic country/city values so the
 * Active Sessions UI shows a populated location column without anyone needing
 * to install MaxMind GeoLite2 just to click around in dev.
 */
class FakeGeoIpLookup implements GeoIpLookupInterface
{
    /** @var list<array{prefix: string, country: string, city: string}> */
    protected const MAPPINGS = [
        ['prefix' => '127.', 'country' => 'CZ', 'city' => 'Localhost'],
        ['prefix' => '172.', 'country' => 'CZ', 'city' => 'Prague (Docker)'],
        ['prefix' => '10.', 'country' => 'CZ', 'city' => 'Prague (Internal)'],
        ['prefix' => '192.168.', 'country' => 'CZ', 'city' => 'Prague (LAN)'],
        ['prefix' => '192.0.2.', 'country' => 'US', 'city' => 'New York'],
        ['prefix' => '198.51.100.', 'country' => 'GB', 'city' => 'London'],
        ['prefix' => '203.0.113.', 'country' => 'DE', 'city' => 'Berlin'],
    ];

    public function lookup(?string $ipAddress): ?GeoIpResult
    {
        if ($ipAddress === null || $ipAddress === '') {
            return null;
        }

        foreach (self::MAPPINGS as $mapping) {
            if (str_starts_with($ipAddress, $mapping['prefix'])) {
                return new GeoIpResult($mapping['country'], $mapping['city']);
            }
        }

        return null;
    }
}
