<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Passkey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\PasskeyRelyingPartyEntityFactory;

#[CoversClass(PasskeyRelyingPartyEntityFactory::class)]
class PasskeyRelyingPartyEntityFactoryTest extends TestCase
{
    public function testCreateProducesEntityWithConfiguredIdAndName(): void
    {
        $factory = new PasskeyRelyingPartyEntityFactory('shop.example.com', 'Example Shop');

        $entity = $factory->create();

        self::assertSame('shop.example.com', $entity->id);
        self::assertSame('Example Shop', $entity->name);
        self::assertSame('shop.example.com', $factory->getRpId());
    }
}
