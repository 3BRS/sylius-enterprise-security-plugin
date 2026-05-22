<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpWhitelist\CidrMatcher;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpBlacklistInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpBlacklistRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpBlacklist\IpBlacklistChecker;

#[CoversClass(IpBlacklistChecker::class)]
class IpBlacklistCheckerTest extends TestCase
{
    /**
     * @param list<string>                        $globalCidrs
     * @param list<AdminUserIpBlacklistInterface> $allEnabled
     */
    private function createChecker(
        bool $enabled,
        array $globalCidrs,
        ?AdminUserIpBlacklistInterface $perAdmin = null,
        array $allEnabled = [],
    ): IpBlacklistChecker {
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

        $repository = $this->createStub(AdminUserIpBlacklistRepositoryInterface::class);
        $repository->method('findOneByUser')->willReturn($perAdmin);
        $repository->method('findAllEnabled')->willReturn($allEnabled);

        return new IpBlacklistChecker($settings, $repository, new CidrMatcher());
    }

    private function admin(): AdminUserInterface
    {
        return $this->createStub(AdminUserInterface::class);
    }

    /**
     * @param list<string> $cidrs
     */
    private function perAdmin(bool $enabled, array $cidrs): AdminUserIpBlacklistInterface
    {
        $blacklist = $this->createStub(AdminUserIpBlacklistInterface::class);
        $blacklist->method('isEnabled')->willReturn($enabled);
        $blacklist->method('getCidrs')->willReturn($cidrs);

        return $blacklist;
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

    public function testPerAdminBlocksWhenGlobalDoesNot(): void
    {
        $perAdmin = $this->perAdmin(true, ['172.16.0.0/12']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], $perAdmin);

        self::assertTrue($checker->isBlockedForAdmin($this->admin(), '172.16.5.1'));
    }

    public function testGlobalBlocksEvenWithoutPerAdmin(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8'], null);

        self::assertTrue($checker->isBlockedForAdmin($this->admin(), '10.5.6.7'));
    }

    public function testDisabledPerAdminIsIgnored(): void
    {
        $perAdmin = $this->perAdmin(false, ['172.16.0.0/12']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], $perAdmin);

        self::assertFalse($checker->isBlockedForAdmin($this->admin(), '172.16.5.1'));
    }

    public function testNoMatchAnywhereAllows(): void
    {
        $perAdmin = $this->perAdmin(true, ['172.16.0.0/12']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], $perAdmin);

        self::assertFalse($checker->isBlockedForAdmin($this->admin(), '8.8.8.8'));
    }

    public function testEmptyIpNotMatched(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8']);
        self::assertFalse($checker->isBlockedByGlobal(''));
    }

    public function testGetGlobalCidrsFiltersNonStrings(): void
    {
        $settings = $this->createStub(SettingsProviderInterface::class);
        $settings->method('get')->willReturn(['10.0.0.0/8', '', 42, null, '192.168.1.1']);
        $repository = $this->createStub(AdminUserIpBlacklistRepositoryInterface::class);

        $checker = new IpBlacklistChecker($settings, $repository, new CidrMatcher());

        self::assertSame(['10.0.0.0/8', '192.168.1.1'], $checker->getGlobalCidrs());
    }

    public function testAnonymousBlockedByGlobal(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8']);

        self::assertTrue($checker->isBlockedAnonymously('10.5.6.7'));
    }

    public function testAnonymousBlockedByAnyPerAdminWhenGlobalEmpty(): void
    {
        $entry = $this->perAdmin(true, ['192.168.1.0/24']);
        $checker = $this->createChecker(true, [], null, [$entry]);

        self::assertTrue($checker->isBlockedAnonymously('192.168.1.42'));
    }

    public function testAnonymousAllowedWhenNothingMatches(): void
    {
        $entry = $this->perAdmin(true, ['192.168.1.0/24']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], null, [$entry]);

        self::assertFalse($checker->isBlockedAnonymously('8.8.8.8'));
    }

    public function testAnonymousEmptyIpAllowed(): void
    {
        $entry = $this->perAdmin(true, ['127.0.0.1']);
        $checker = $this->createChecker(true, [], null, [$entry]);

        self::assertFalse($checker->isBlockedAnonymously(''));
    }
}
