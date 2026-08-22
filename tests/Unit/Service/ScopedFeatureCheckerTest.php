<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ScopedFeatureChecker;

#[CoversClass(ScopedFeatureChecker::class)]
class ScopedFeatureCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, bool, bool}>
     */
    public static function switchProvider(): iterable
    {
        yield 'both on' => [true, true, true];
        // The switch on the settings page has to be able to stop a feature the
        // installation set up; before this it changed the menu and nothing else.
        yield 'settings off' => [true, false, false];
        // And the settings page must not be able to start one the configuration
        // never set up — passkeys without a relying-party id have nothing to run.
        yield 'configuration off' => [false, true, false];
        yield 'both off' => [false, false, false];
    }

    #[DataProvider('switchProvider')]
    public function testAFeatureIsInEffectOnlyWhenBothSwitchesAgree(bool $configured, bool $settings, bool $expected): void
    {
        $checker = new ScopedFeatureChecker($this->makeToggle($settings), 'passkey', $configured, $configured);

        self::assertSame($expected, $checker->isEnabled(SettingsScope::CUSTOMER));
    }

    public function testEachGroupReadsItsOwnConfigurationFlag(): void
    {
        $checker = new ScopedFeatureChecker(
            $this->makeToggle(true),
            'session_management',
            customerConfigured: true,
            adminConfigured: false,
        );

        self::assertTrue($checker->isEnabled(SettingsScope::CUSTOMER));
        self::assertFalse($checker->isEnabled(SettingsScope::ADMIN));
    }

    public function testTheFeatureNameIsPassedThroughToTheToggle(): void
    {
        $asked = null;
        $toggle = $this->createStub(FeatureToggleInterface::class);
        $toggle->method('isEnabled')->willReturnCallback(
            function (string $feature) use (&$asked): bool {
                $asked = $feature;

                return true;
            },
        );

        (new ScopedFeatureChecker($toggle, 'login_notifications', true, true))->isEnabled(SettingsScope::ADMIN);

        self::assertSame('login_notifications', $asked);
    }

    protected function makeToggle(bool $answer): FeatureToggleInterface
    {
        $toggle = $this->createStub(FeatureToggleInterface::class);
        $toggle->method('isEnabled')->willReturn($answer);

        return $toggle;
    }
}
