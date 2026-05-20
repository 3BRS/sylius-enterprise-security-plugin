<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpWhitelistInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpWhitelistRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpWhitelist\IpWhitelistChecker;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;

#[CoversClass(IpWhitelistChecker::class)]
class IpWhitelistCheckerTest extends TestCase
{
    /**
     * @param list<string>                        $globalCidrs
     * @param list<AdminUserIpWhitelistInterface> $allEnabled
     */
    private function createChecker(
        bool $enabled,
        array $globalCidrs,
        ?AdminUserIpWhitelistInterface $perAdmin = null,
        array $allEnabled = [],
    ): IpWhitelistChecker {
        $settings = $this->createStub(SettingsProviderInterface::class);
        $settings->method('getBool')->willReturnCallback(static function (string $path, SettingsScope $scope) use ($enabled): bool {
            if ($path === 'ip_whitelist.enabled' && $scope === SettingsScope::GLOBAL) {
                return $enabled;
            }

            return false;
        });
        $settings->method('get')->willReturnCallback(static function (string $path, SettingsScope $scope) use ($globalCidrs): mixed {
            if ($path === 'ip_whitelist.global_cidrs' && $scope === SettingsScope::GLOBAL) {
                return $globalCidrs;
            }

            return null;
        });

        $repository = $this->createStub(AdminUserIpWhitelistRepositoryInterface::class);
        $repository->method('findOneByAdminUser')->willReturn($perAdmin);
        $repository->method('findAllEnabled')->willReturn($allEnabled);

        return new IpWhitelistChecker($settings, $repository);
    }

    private function admin(): AdminUserInterface
    {
        return $this->createStub(AdminUserInterface::class);
    }

    /**
     * @param list<string> $cidrs
     */
    private function perAdmin(bool $enabled, array $cidrs): AdminUserIpWhitelistInterface
    {
        $whitelist = $this->createStub(AdminUserIpWhitelistInterface::class);
        $whitelist->method('isEnabled')->willReturn($enabled);
        $whitelist->method('getCidrs')->willReturn($cidrs);

        return $whitelist;
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

    public function testEmptyGlobalDeniesAnyIp(): void
    {
        $checker = $this->createChecker(true, []);
        self::assertFalse($checker->isAllowedByGlobal('1.2.3.4'));
    }

    public function testIpv4MatchesGlobalCidr(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8']);
        self::assertTrue($checker->isAllowedByGlobal('10.5.6.7'));
        self::assertFalse($checker->isAllowedByGlobal('11.0.0.1'));
    }

    public function testIpv4ExactMatch(): void
    {
        $checker = $this->createChecker(true, ['192.168.1.1']);
        self::assertTrue($checker->isAllowedByGlobal('192.168.1.1'));
        self::assertFalse($checker->isAllowedByGlobal('192.168.1.2'));
    }

    public function testIpv6MatchesCidr(): void
    {
        $checker = $this->createChecker(true, ['2001:db8::/32']);
        self::assertTrue($checker->isAllowedByGlobal('2001:db8::1'));
        self::assertFalse($checker->isAllowedByGlobal('2002:db8::1'));
    }

    public function testPerAdminAllowsWhenGlobalDoesNot(): void
    {
        $perAdmin = $this->perAdmin(true, ['172.16.0.0/12']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], $perAdmin);

        self::assertTrue($checker->isAllowedForAdmin($this->admin(), '172.16.5.1'));
    }

    public function testGlobalAllowsEvenWithoutPerAdmin(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8'], null);

        self::assertTrue($checker->isAllowedForAdmin($this->admin(), '10.5.6.7'));
    }

    public function testDisabledPerAdminIsIgnored(): void
    {
        $perAdmin = $this->perAdmin(false, ['172.16.0.0/12']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], $perAdmin);

        self::assertFalse($checker->isAllowedForAdmin($this->admin(), '172.16.5.1'));
    }

    public function testNoMatchAnywhereDenies(): void
    {
        $perAdmin = $this->perAdmin(true, ['172.16.0.0/12']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], $perAdmin);

        self::assertFalse($checker->isAllowedForAdmin($this->admin(), '8.8.8.8'));
    }

    public function testEmptyIpDenies(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8']);
        self::assertFalse($checker->isAllowedByGlobal(''));
    }

    public function testGetGlobalCidrsFiltersNonStrings(): void
    {
        $settings = $this->createStub(SettingsProviderInterface::class);
        $settings->method('get')->willReturn(['10.0.0.0/8', '', 42, null, '192.168.1.1']);
        $repository = $this->createStub(AdminUserIpWhitelistRepositoryInterface::class);

        $checker = new IpWhitelistChecker($settings, $repository);

        self::assertSame(['10.0.0.0/8', '192.168.1.1'], $checker->getGlobalCidrs());
    }

    public function testAnonymousAllowedByGlobal(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8']);

        self::assertTrue($checker->isAllowedAnonymously('10.5.6.7'));
    }

    public function testAnonymousAllowedByAnyPerAdminWhenGlobalEmpty(): void
    {
        $entry = $this->perAdmin(true, ['192.168.1.0/24']);
        $checker = $this->createChecker(true, [], null, [$entry]);

        self::assertTrue($checker->isAllowedAnonymously('192.168.1.42'));
    }

    public function testAnonymousDeniedWhenNothingMatches(): void
    {
        $entry = $this->perAdmin(true, ['192.168.1.0/24']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], null, [$entry]);

        self::assertFalse($checker->isAllowedAnonymously('8.8.8.8'));
    }

    public function testAnonymousEmptyIpDenied(): void
    {
        $entry = $this->perAdmin(true, ['127.0.0.1']);
        $checker = $this->createChecker(true, [], null, [$entry]);

        self::assertFalse($checker->isAllowedAnonymously(''));
    }
}
