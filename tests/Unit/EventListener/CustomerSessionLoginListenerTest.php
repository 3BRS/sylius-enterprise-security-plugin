<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\CustomerSessionLoginListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\CustomerLoginNotificationEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerNewDeviceDetectorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\SessionFingerprintGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentInfo;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentParserInterface;

#[CoversClass(CustomerSessionLoginListener::class)]
class CustomerSessionLoginListenerTest extends TestCase
{
    public function testSkipsEntirelyWhenBothFlagsAreOff(): void
    {
        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('track');
        $emailManager = $this->createMock(CustomerLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendNewDeviceNotification');

        $listener = $this->makeListener($tracker, $emailManager, false, false);
        $listener->onLoginSuccess($this->makeEvent($this->createStub(ShopUserInterface::class)));
    }

    public function testSkipsForNonShopUser(): void
    {
        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('track');

        $emailManager = $this->createMock(CustomerLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendNewDeviceNotification');

        $listener = $this->makeListener($tracker, $emailManager, true, true);
        $listener->onLoginSuccess($this->makeEvent($this->createStub(UserInterface::class)));
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

        $listener = $this->makeListener($tracker, $emailManager, true, false, $detector);
        $listener->onLoginSuccess($this->makeEvent($user));
    }

    public function testSendsNotificationOnNewDevice(): void
    {
        $user = $this->createStub(ShopUserInterface::class);

        $detector = $this->createStub(CustomerNewDeviceDetectorInterface::class);
        $detector->method('checkAndRemember')->willReturn(true);

        $emailManager = $this->createMock(CustomerLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::once())->method('sendNewDeviceNotification');

        $listener = $this->makeListener(
            $this->createStub(CustomerSessionTrackerInterface::class),
            $emailManager,
            false,
            true,
            $detector,
        );
        $listener->onLoginSuccess($this->makeEvent($user));
    }

    public function testSkipsNotificationOnKnownDevice(): void
    {
        $user = $this->createStub(ShopUserInterface::class);

        $detector = $this->createStub(CustomerNewDeviceDetectorInterface::class);
        $detector->method('checkAndRemember')->willReturn(false);

        $emailManager = $this->createMock(CustomerLoginNotificationEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendNewDeviceNotification');

        $listener = $this->makeListener(
            $this->createStub(CustomerSessionTrackerInterface::class),
            $emailManager,
            false,
            true,
            $detector,
        );
        $listener->onLoginSuccess($this->makeEvent($user));
    }

    protected function makeListener(
        CustomerSessionTrackerInterface $tracker,
        CustomerLoginNotificationEmailManagerInterface $emailManager,
        bool $sessionTrackingEnabled,
        bool $loginNotificationsEnabled,
        ?CustomerNewDeviceDetectorInterface $detector = null,
    ): CustomerSessionLoginListener {
        $fingerprintGenerator = $this->createStub(SessionFingerprintGeneratorInterface::class);
        $fingerprintGenerator->method('generate')->willReturn('fp-1');

        $userAgentParser = $this->createStub(UserAgentParserInterface::class);
        $userAgentParser->method('parse')->willReturn(new UserAgentInfo('Chrome', 'Mac', 'desktop'));

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-30 10:00:00'));

        return new CustomerSessionLoginListener(
            $tracker,
            $detector ?? $this->createStub(CustomerNewDeviceDetectorInterface::class),
            $emailManager,
            $fingerprintGenerator,
            $userAgentParser,
            $clock,
            $sessionTrackingEnabled,
            $loginNotificationsEnabled,
        );
    }

    protected function makeEvent(object $user): LoginSuccessEvent
    {
        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('the-session-id');

        $request = $this->createStub(Request::class);
        $request->headers = new HeaderBag(['User-Agent' => 'Mozilla/5.0']);
        $request->method('getClientIp')->willReturn('127.0.0.1');
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn($request);

        return $event;
    }
}
