<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\CustomerLoginNotificationEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerNewDeviceDetectorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionLoginHandler;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\GeoIpLookupInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\GeoIpResult;
use ThreeBRS\EnterpriseSecurityBundle\Session\SessionFingerprintGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentInfo;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentParserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ScopedFeatureCheckerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

#[CoversClass(CustomerSessionLoginHandler::class)]
class CustomerSessionLoginHandlerTest extends TestCase
{
    public function testSkipsEntirelyWhenBothFlagsAreOff(): void
    {
        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('track');
        $emailManager = $this->createMock(CustomerLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendNewDeviceNotification');

        $handler = $this->makeHandler($tracker, $emailManager, false, false);
        $handler->handle($this->createStub(ShopUserInterface::class), $this->makeRequest());
    }

    public function testTracksSessionAndSkipsNotificationWhenLoginNotificationsDisabled(): void
    {
        $user = $this->createStub(ShopUserInterface::class);

        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::once())->method('track');

        $detector = $this->createMock(CustomerNewDeviceDetectorInterface::class);
        $detector->expects(self::never())->method('checkAndRemember');

        $emailManager = $this->createMock(CustomerLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendNewDeviceNotification');

        $handler = $this->makeHandler($tracker, $emailManager, true, false, $detector);
        $handler->handle($user, $this->makeRequest());
    }

    public function testSendsNotificationOnNewDeviceWithGeoIpLocation(): void
    {
        $user = $this->createStub(ShopUserInterface::class);

        $detector = $this->createStub(CustomerNewDeviceDetectorInterface::class);
        $detector->method('checkAndRemember')->willReturn(true);

        $geoIpLookup = $this->createStub(GeoIpLookupInterface::class);
        $geoIpLookup->method('lookup')->willReturn(new GeoIpResult('US', 'New York'));

        $emailManager = $this->createMock(CustomerLoginNotificationEmailManagerInterface::class);
        $emailManager
            ->expects(self::once())
            ->method('sendNewDeviceNotification')
            ->with(
                $user,
                self::isInstanceOf(\DateTimeImmutable::class),
                '127.0.0.1',
                'US',
                'New York',
                self::isInstanceOf(UserAgentInfo::class),
            )
        ;

        $handler = $this->makeHandler(
            $this->createStub(CustomerSessionTrackerInterface::class),
            $emailManager,
            false,
            true,
            $detector,
            $geoIpLookup,
        );
        $handler->handle($user, $this->makeRequest());
    }

    public function testSendsNotificationOnNewDeviceWithoutGeoIpWhenLookupReturnsNull(): void
    {
        $user = $this->createStub(ShopUserInterface::class);

        $detector = $this->createStub(CustomerNewDeviceDetectorInterface::class);
        $detector->method('checkAndRemember')->willReturn(true);

        $geoIpLookup = $this->createStub(GeoIpLookupInterface::class);
        $geoIpLookup->method('lookup')->willReturn(null);

        $emailManager = $this->createMock(CustomerLoginNotificationEmailManagerInterface::class);
        $emailManager
            ->expects(self::once())
            ->method('sendNewDeviceNotification')
            ->with(
                $user,
                self::isInstanceOf(\DateTimeImmutable::class),
                '127.0.0.1',
                null,
                null,
                self::isInstanceOf(UserAgentInfo::class),
            )
        ;

        $handler = $this->makeHandler(
            $this->createStub(CustomerSessionTrackerInterface::class),
            $emailManager,
            false,
            true,
            $detector,
            $geoIpLookup,
        );
        $handler->handle($user, $this->makeRequest());
    }

    public function testSkipsNotificationOnKnownDevice(): void
    {
        $user = $this->createStub(ShopUserInterface::class);

        $detector = $this->createStub(CustomerNewDeviceDetectorInterface::class);
        $detector->method('checkAndRemember')->willReturn(false);

        $emailManager = $this->createMock(CustomerLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendNewDeviceNotification');

        $handler = $this->makeHandler(
            $this->createStub(CustomerSessionTrackerInterface::class),
            $emailManager,
            false,
            true,
            $detector,
        );
        $handler->handle($user, $this->makeRequest());
    }

    protected function makeHandler(
        CustomerSessionTrackerInterface $tracker,
        CustomerLoginNotificationEmailManagerInterface $emailManager,
        bool $sessionTrackingEnabled,
        bool $loginNotificationsEnabled,
        ?CustomerNewDeviceDetectorInterface $detector = null,
        ?GeoIpLookupInterface $geoIpLookup = null,
    ): CustomerSessionLoginHandler {
        $fingerprintGenerator = $this->createStub(SessionFingerprintGeneratorInterface::class);
        $fingerprintGenerator->method('generate')->willReturn('fp-1');

        $userAgentParser = $this->createStub(UserAgentParserInterface::class);
        $userAgentParser->method('parse')->willReturn(new UserAgentInfo('Chrome', 'Mac', 'desktop'));

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-30 10:00:00'));

        return new CustomerSessionLoginHandler(
            $tracker,
            $detector ?? $this->createStub(CustomerNewDeviceDetectorInterface::class),
            $emailManager,
            $fingerprintGenerator,
            $userAgentParser,
            $geoIpLookup ?? $this->createStub(GeoIpLookupInterface::class),
            $clock,
            $this->makeFeature($sessionTrackingEnabled),
            $this->makeFeature($loginNotificationsEnabled),
        );
    }

    protected function makeRequest(): Request
    {
        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('the-session-id');

        $request = $this->createStub(Request::class);
        $request->headers = new HeaderBag(['User-Agent' => 'Mozilla/5.0']);
        $request->method('getClientIp')->willReturn('127.0.0.1');
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);

        return $request;
    }

    /**
     * The two switches a feature answers to are combined behind this checker, so the
     * tests stub the answer rather than the configuration flag on its own.
     */
    protected function makeFeature(bool $customerEnabled, ?bool $adminEnabled = null): ScopedFeatureCheckerInterface
    {
        $adminEnabled ??= $customerEnabled;

        $checker = $this->createStub(ScopedFeatureCheckerInterface::class);
        $checker->method('isEnabled')->willReturnCallback(
            static fn (SettingsScope $scope): bool => $scope === SettingsScope::ADMIN ? $adminEnabled : $customerEnabled,
        );

        return $checker;
    }

}
