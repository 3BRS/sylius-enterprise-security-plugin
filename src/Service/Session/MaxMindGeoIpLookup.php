<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;

/**
 * Looks up country + city for a given IP using a local MaxMind GeoLite2 / GeoIP2
 * `.mmdb` database via `geoip2/geoip2`.
 *
 * The plugin does **not** require `geoip2/geoip2` — it's listed under composer
 * `suggest`. Wire this service up only after running
 * `composer require geoip2/geoip2` in the application and providing a path to
 * a `.mmdb` file (e.g. the free GeoLite2-City download from MaxMind).
 */
class MaxMindGeoIpLookup implements GeoIpLookupInterface
{
    protected ?Reader $reader = null;

    public function __construct(
        protected string $databasePath,
    ) {
    }

    public function lookup(?string $ipAddress): ?GeoIpResult
    {
        if ($ipAddress === null || $ipAddress === '') {
            return null;
        }

        try {
            $record = $this->reader()->city($ipAddress);
        } catch (AddressNotFoundException) {
            // IP isn't covered by the database (private ranges, unallocated blocks).
            return null;
        } catch (\InvalidArgumentException) {
            // Reader rejects malformed IP strings; treat the same as "not found".
            return null;
        }

        return new GeoIpResult(
            $record->country->isoCode,
            $record->city->name,
        );
    }

    protected function reader(): Reader
    {
        return $this->reader ??= new Reader($this->databasePath);
    }
}
