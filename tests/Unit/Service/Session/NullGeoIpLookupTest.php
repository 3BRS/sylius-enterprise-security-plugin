<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\NullGeoIpLookup;

#[CoversClass(NullGeoIpLookup::class)]
class NullGeoIpLookupTest extends TestCase
{
    public function testAlwaysReturnsNull(): void
    {
        $lookup = new NullGeoIpLookup();

        self::assertNull($lookup->lookup(null));
        self::assertNull($lookup->lookup(''));
        self::assertNull($lookup->lookup('8.8.8.8'));
        self::assertNull($lookup->lookup('2001:db8::1'));
    }
}
