<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasskeyChecker;

#[CoversClass(PasskeyChecker::class)]
class PasskeyCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, bool, bool}>
     */
    public static function switchProvider(): iterable
    {
        yield 'both on' => [true, true, true];
        yield 'settings off' => [true, false, false];
        // The pairing that matters: ticked in Security Settings on an installation
        // whose configuration leaves passkeys off, where the login endpoint refuses
        // the credential anyway.
        yield 'configuration off' => [false, true, false];
        yield 'both off' => [false, false, false];
    }

    #[DataProvider('switchProvider')]
    public function testAPasskeyOpensASessionOnlyWhenBothSwitchesAgree(bool $configured, bool $settings, bool $expected): void
    {
        $featureToggle = $this->createStub(FeatureToggleInterface::class);
        $featureToggle->method('isEnabled')->willReturn($settings);

        $checker = new PasskeyChecker($featureToggle, $configured, $configured);

        self::assertSame($expected, $checker->isEnabled(SettingsScope::CUSTOMER));
    }

    public function testEachScopeReadsItsOwnConfiguration(): void
    {
        $featureToggle = $this->createStub(FeatureToggleInterface::class);
        $featureToggle->method('isEnabled')->willReturn(true);

        $checker = new PasskeyChecker($featureToggle, customerConfigured: true, adminConfigured: false);

        self::assertTrue($checker->isEnabled(SettingsScope::CUSTOMER));
        self::assertFalse($checker->isEnabled(SettingsScope::ADMIN));
    }
}
