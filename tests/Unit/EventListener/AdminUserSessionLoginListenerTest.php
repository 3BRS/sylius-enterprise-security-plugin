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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\SessionFingerprintGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentInfo;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentParserInterface;

#[CoversClass(AdminUserSessionLoginListener::class)]
class AdminUserSessionLoginListenerTest extends TestCase
{
    public function testTracksAdminSession(): void
    {
        $tracker = $this->createMock(AdminUserSessionTrackerInterface::class);
        $tracker->expects(self::once())->method('track');

        $listener = $this->makeListener($tracker, true, false);
        $listener->onLoginSuccess($this->makeEvent($this->createStub(AdminUserInterface::class)));
    }

    public function testSkipsForNonAdmin(): void
    {
        $tracker = $this->createMock(AdminUserSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('track');

        $listener = $this->makeListener($tracker, true, true);
        $listener->onLoginSuccess($this->makeEvent($this->createStub(UserInterface::class)));
    }

    protected function makeListener(
        AdminUserSessionTrackerInterface $tracker,
        bool $sessionEnabled,
        bool $notificationsEnabled,
    ): AdminUserSessionLoginListener {
        $detector = $this->createStub(AdminUserNewDeviceDetectorInterface::class);
        $detector->method('checkAndRemember')->willReturn(true);

        $emailManager = $this->createStub(AdminUserLoginNotificationEmailManagerInterface::class);

        $fingerprintGenerator = $this->createStub(SessionFingerprintGeneratorInterface::class);
        $fingerprintGenerator->method('generate')->willReturn('fp-1');

        $userAgentParser = $this->createStub(UserAgentParserInterface::class);
        $userAgentParser->method('parse')->willReturn(new UserAgentInfo(null, null, null));

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-30 10:00:00'));

        return new AdminUserSessionLoginListener(
            $tracker,
            $detector,
            $emailManager,
            $fingerprintGenerator,
            $userAgentParser,
            $clock,
            $sessionEnabled,
            $notificationsEnabled,
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
