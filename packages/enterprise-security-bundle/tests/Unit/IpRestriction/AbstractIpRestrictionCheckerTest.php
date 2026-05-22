<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\EnterpriseSecurityBundle\Unit\IpRestriction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpRestriction\AbstractIpRestrictionChecker;
use ThreeBRS\EnterpriseSecurityBundle\IpRestriction\IpRestrictionRecordInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpRestriction\IpRestrictionRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\IpWhitelist\CidrMatcher;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

#[CoversClass(AbstractIpRestrictionChecker::class)]
class AbstractIpRestrictionCheckerTest extends TestCase
{
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

    public function testEmptyGlobalDoesNotMatch(): void
    {
        $checker = $this->createChecker(true, []);
        self::assertFalse($checker->matchesGlobal('1.2.3.4'));
    }

    public function testIpv4MatchesGlobalCidr(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8']);
        self::assertTrue($checker->matchesGlobal('10.5.6.7'));
        self::assertFalse($checker->matchesGlobal('11.0.0.1'));
    }

    public function testIpv6MatchesCidr(): void
    {
        $checker = $this->createChecker(true, ['2001:db8::/32']);
        self::assertTrue($checker->matchesGlobal('2001:db8::1'));
        self::assertFalse($checker->matchesGlobal('2002:db8::1'));
    }

    public function testPerUserMatchesWhenGlobalDoesNot(): void
    {
        $perUser = $this->perUser(true, ['172.16.0.0/12']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], $perUser);

        self::assertTrue($checker->matchesForUser($this->user(), '172.16.5.1'));
    }

    public function testGlobalShortCircuitsPerUserLookup(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8'], null);

        self::assertTrue($checker->matchesForUser($this->user(), '10.5.6.7'));
    }

    public function testDisabledPerUserIsIgnored(): void
    {
        $perUser = $this->perUser(false, ['172.16.0.0/12']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], $perUser);

        self::assertFalse($checker->matchesForUser($this->user(), '172.16.5.1'));
    }

    public function testNoMatchAnywhere(): void
    {
        $perUser = $this->perUser(true, ['172.16.0.0/12']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], $perUser);

        self::assertFalse($checker->matchesForUser($this->user(), '8.8.8.8'));
    }

    public function testEmptyIpNeverMatches(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8']);
        self::assertFalse($checker->matchesGlobal(''));
    }

    public function testGetGlobalCidrsFiltersNonStrings(): void
    {
        $settings = $this->createStub(SettingsProviderInterface::class);
        $settings->method('get')->willReturn(['10.0.0.0/8', '', 42, null, '192.168.1.1']);
        $repository = $this->createStub(IpRestrictionRepositoryInterface::class);

        $checker = new class($settings, $repository, new CidrMatcher()) extends AbstractIpRestrictionChecker {
            protected function getSettingsKey(): string
            {
                return 'ip_restriction';
            }

            protected function getScope(): SettingsScope
            {
                return SettingsScope::ADMIN;
            }
        };

        self::assertSame(['10.0.0.0/8', '192.168.1.1'], $checker->getGlobalCidrs());
    }

    public function testAnonymousMatchesGlobal(): void
    {
        $checker = $this->createChecker(true, ['10.0.0.0/8']);

        self::assertTrue($checker->matchesAnonymously('10.5.6.7'));
    }

    public function testAnonymousMatchesAnyPerUserWhenGlobalEmpty(): void
    {
        $entry = $this->perUser(true, ['192.168.1.0/24']);
        $checker = $this->createChecker(true, [], null, [$entry]);

        self::assertTrue($checker->matchesAnonymously('192.168.1.42'));
    }

    public function testAnonymousDoesNotMatchWhenNothingDoes(): void
    {
        $entry = $this->perUser(true, ['192.168.1.0/24']);
        $checker = $this->createChecker(true, ['10.0.0.0/8'], null, [$entry]);

        self::assertFalse($checker->matchesAnonymously('8.8.8.8'));
    }

    public function testAnonymousEmptyIpDoesNotMatch(): void
    {
        $entry = $this->perUser(true, ['127.0.0.1']);
        $checker = $this->createChecker(true, [], null, [$entry]);

        self::assertFalse($checker->matchesAnonymously(''));
    }

    /**
     * @param list<string>                       $globalCidrs
     * @param list<IpRestrictionRecordInterface> $allEnabled
     */
    private function createChecker(
        bool $enabled,
        array $globalCidrs,
        ?IpRestrictionRecordInterface $perUser = null,
        array $allEnabled = [],
        string $settingsKey = 'ip_restriction',
        SettingsScope $scope = SettingsScope::ADMIN,
    ): AbstractIpRestrictionChecker {
        $settings = $this->createStub(SettingsProviderInterface::class);
        $settings->method('getBool')->willReturnCallback(static function (string $path, SettingsScope $s) use ($enabled, $settingsKey, $scope): bool {
            if ($path === $settingsKey . '.enabled' && $s === $scope) {
                return $enabled;
            }

            return false;
        });
        $settings->method('get')->willReturnCallback(static function (string $path, SettingsScope $s) use ($globalCidrs, $settingsKey, $scope): mixed {
            if ($path === $settingsKey . '.global_cidrs' && $s === $scope) {
                return $globalCidrs;
            }

            return null;
        });

        $repository = $this->createStub(IpRestrictionRepositoryInterface::class);
        $repository->method('findOneByUser')->willReturn($perUser);
        $repository->method('findAllEnabled')->willReturn($allEnabled);

        return new class($settings, $repository, new CidrMatcher(), $settingsKey, $scope) extends AbstractIpRestrictionChecker {
            public function __construct(
                SettingsProviderInterface $settings,
                IpRestrictionRepositoryInterface $repository,
                CidrMatcher $cidrMatcher,
                protected string $settingsKey,
                protected SettingsScope $scope,
            ) {
                parent::__construct($settings, $repository, $cidrMatcher);
            }

            protected function getSettingsKey(): string
            {
                return $this->settingsKey;
            }

            protected function getScope(): SettingsScope
            {
                return $this->scope;
            }
        };
    }

    private function user(): UserInterface
    {
        return $this->createStub(UserInterface::class);
    }

    /**
     * @param list<string> $cidrs
     */
    private function perUser(bool $enabled, array $cidrs): IpRestrictionRecordInterface
    {
        $record = $this->createStub(IpRestrictionRecordInterface::class);
        $record->method('isEnabled')->willReturn($enabled);
        $record->method('getCidrs')->willReturn($cidrs);

        return $record;
    }
}
