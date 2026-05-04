<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\AdminUserSessionLoginListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AdminUserLoginNotificationEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserNewDeviceDetectorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionTrackerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpLookupInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpResult;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\SessionFingerprintGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentInfo;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentParserInterface;

#[CoversClass(AdminUserSessionLoginListener::class)]
class AdminUserSessionLoginListenerTest extends TestCase
{
    public function testSkipsEntirelyWhenBothFlagsAreOff(): void
    {
        $tracker = $this->createMock(AdminUserSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('track');
        $emailManager = $this->createMock(AdminUserLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendNewDeviceNotification');

        $listener = $this->makeListener($tracker, $emailManager, false, false);
        $listener->onLoginSuccess($this->makeEvent($this->createStub(AdminUserInterface::class)));
    }

    public function testSkipsForNonAdmin(): void
    {
        $tracker = $this->createMock(AdminUserSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('track');

        $emailManager = $this->createMock(AdminUserLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendNewDeviceNotification');

        $listener = $this->makeListener($tracker, $emailManager, true, true);
        $listener->onLoginSuccess($this->makeEvent($this->createStub(UserInterface::class)));
    }

    public function testTracksSessionAndSkipsNotificationWhenLoginNotificationsDisabled(): void
    {
        $user = $this->createStub(AdminUserInterface::class);

        $tracker = $this->createMock(AdminUserSessionTrackerInterface::class);
        $tracker->expects(self::once())->method('track');

        $detector = $this->createMock(AdminUserNewDeviceDetectorInterface::class);
        $detector->expects(self::never())->method('checkAndRemember');

        $emailManager = $this->createMock(AdminUserLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendNewDeviceNotification');

        $listener = $this->makeListener($tracker, $emailManager, true, false, $detector);
        $listener->onLoginSuccess($this->makeEvent($user));
    }

    public function testSendsNotificationOnNewDeviceWithGeoIpLocation(): void
    {
        $user = $this->createStub(AdminUserInterface::class);

        $detector = $this->createStub(AdminUserNewDeviceDetectorInterface::class);
        $detector->method('checkAndRemember')->willReturn(true);

        $geoIpLookup = $this->createStub(GeoIpLookupInterface::class);
        $geoIpLookup->method('lookup')->willReturn(new GeoIpResult('CZ', 'Prague'));

        $emailManager = $this->createMock(AdminUserLoginNotificationEmailManagerInterface::class);
        $emailManager
            ->expects(self::once())
            ->method('sendNewDeviceNotification')
            ->with(
                $user,
                self::isInstanceOf(\DateTimeImmutable::class),
                '1.2.3.4',
                'CZ',
                'Prague',
                self::isInstanceOf(UserAgentInfo::class),
            )
        ;

        $listener = $this->makeListener(
            $this->createStub(AdminUserSessionTrackerInterface::class),
            $emailManager,
            false,
            true,
            $detector,
            $geoIpLookup,
        );
        $listener->onLoginSuccess($this->makeEvent($user));
    }

    public function testSendsNotificationOnNewDeviceWithoutGeoIpWhenLookupReturnsNull(): void
    {
        $user = $this->createStub(AdminUserInterface::class);

        $detector = $this->createStub(AdminUserNewDeviceDetectorInterface::class);
        $detector->method('checkAndRemember')->willReturn(true);

        $geoIpLookup = $this->createStub(GeoIpLookupInterface::class);
        $geoIpLookup->method('lookup')->willReturn(null);

        $emailManager = $this->createMock(AdminUserLoginNotificationEmailManagerInterface::class);
        $emailManager
            ->expects(self::once())
            ->method('sendNewDeviceNotification')
            ->with(
                $user,
                self::isInstanceOf(\DateTimeImmutable::class),
                '1.2.3.4',
                null,
                null,
                self::isInstanceOf(UserAgentInfo::class),
            )
        ;

        $listener = $this->makeListener(
            $this->createStub(AdminUserSessionTrackerInterface::class),
            $emailManager,
            false,
            true,
            $detector,
            $geoIpLookup,
        );
        $listener->onLoginSuccess($this->makeEvent($user));
    }

    public function testSkipsNotificationOnKnownDevice(): void
    {
        $user = $this->createStub(AdminUserInterface::class);

        $detector = $this->createStub(AdminUserNewDeviceDetectorInterface::class);
        $detector->method('checkAndRemember')->willReturn(false);

        $emailManager = $this->createMock(AdminUserLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendNewDeviceNotification');

        $listener = $this->makeListener(
            $this->createStub(AdminUserSessionTrackerInterface::class),
            $emailManager,
            false,
            true,
            $detector,
        );
        $listener->onLoginSuccess($this->makeEvent($user));
    }

    protected function makeListener(
        AdminUserSessionTrackerInterface $tracker,
        AdminUserLoginNotificationEmailManagerInterface $emailManager,
        bool $sessionTrackingEnabled,
        bool $loginNotificationsEnabled,
        ?AdminUserNewDeviceDetectorInterface $detector = null,
        ?GeoIpLookupInterface $geoIpLookup = null,
    ): AdminUserSessionLoginListener {
        $fingerprintGenerator = $this->createStub(SessionFingerprintGeneratorInterface::class);
        $fingerprintGenerator->method('generate')->willReturn('fp-1');

        $userAgentParser = $this->createStub(UserAgentParserInterface::class);
        $userAgentParser->method('parse')->willReturn(new UserAgentInfo('Firefox', 'Linux', 'desktop'));

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-30 10:00:00'));

        return new AdminUserSessionLoginListener(
            $tracker,
            $detector ?? $this->createStub(AdminUserNewDeviceDetectorInterface::class),
            $emailManager,
            $fingerprintGenerator,
            $userAgentParser,
            $geoIpLookup ?? $this->createStub(GeoIpLookupInterface::class),
            $clock,
            $sessionTrackingEnabled,
            $loginNotificationsEnabled,
        );
    }

    protected function makeEvent(object $user): LoginSuccessEvent
    {
        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('admin-session');

        $request = $this->createStub(Request::class);
        $request->headers = new HeaderBag(['User-Agent' => 'Mozilla/5.0']);
        $request->method('getClientIp')->willReturn('1.2.3.4');
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn($request);

        return $event;
    }
}
