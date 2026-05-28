<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\EnterpriseSecurityBundle\IpWhitelist\CidrMatcher;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpBlacklist\IpBlacklistChecker;

#[CoversClass(IpBlacklistChecker::class)]
class IpBlacklistCheckerTest extends TestCase
{
    /**
     * @param list<string> $globalCidrs
     */
    private function createChecker(bool $enabled, array $globalCidrs): IpBlacklistChecker
    {
        $settings = $this->createStub(SettingsProviderInterface::class);
        $settings->method('getBool')->willReturnCallback(static function (string $path, SettingsScope $scope) use ($enabled): bool {
            if ($path === 'ip_blacklist.enabled' && $scope === SettingsScope::ADMIN) {
                return $enabled;
            }

            return false;
        });
        $settings->method('get')->willReturnCallback(static function (string $path, SettingsScope $scope) use ($globalCidrs): mixed {
            if ($path === 'ip_blacklist.global_cidrs' && $scope === SettingsScope::ADMIN) {
                return $globalCidrs;
            }

            return null;
        });

        return new IpBlacklistChecker($settings, new CidrMatcher());
    }

    public function testFeatureDisabledByDefault(): void
    {
        $checker = $this->createChecker(false, []);
        self::assertFalse($checker->isFeatureEnabled());
    }

    public function testFeatureEnabled(): void
    {
        $checker = $this->createChecker(true, []);
        self::assertTrue($checker->isFeatureEnabled());
    }

    public function testEmptyGlobalAllowsAnyIp(): void
    {
        // Fail-open: empty blacklist = nothing is blocked.
        $checker = $this->createChecker(true, []);
        self::assertFalse($checker->isBlockedByGlobal('1.2.3.4'));
    }

    public function testIpv4MatchesGlobalCidr(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8']);
        self::assertTrue($checker->isBlockedByGlobal('10.5.6.7'));
        self::assertFalse($checker->isBlockedByGlobal('11.0.0.1'));
    }

    public function testIpv4ExactMatch(): void
    {
        $checker = $this->createChecker(true, ['192.168.1.1']);
        self::assertTrue($checker->isBlockedByGlobal('192.168.1.1'));
        self::assertFalse($checker->isBlockedByGlobal('192.168.1.2'));
    }

    public function testIpv6MatchesCidr(): void
    {
        $checker = $this->createChecker(true, ['2001:db8::/32']);
        self::assertTrue($checker->isBlockedByGlobal('2001:db8::1'));
        self::assertFalse($checker->isBlockedByGlobal('2002:db8::1'));
    }

    public function testEmptyIpNotMatched(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8']);
        self::assertFalse($checker->isBlockedByGlobal(''));
    }
}
