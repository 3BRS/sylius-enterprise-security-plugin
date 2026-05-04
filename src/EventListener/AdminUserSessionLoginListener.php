<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AdminUserLoginNotificationEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserNewDeviceDetectorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\AdminUserSessionTrackerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpLookupInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\SessionFingerprintGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentParserInterface;

class AdminUserSessionLoginListener implements AdminUserSessionLoginListenerInterface
{
    public function __construct(
        protected AdminUserSessionTrackerInterface $tracker,
        protected AdminUserNewDeviceDetectorInterface $newDeviceDetector,
        protected AdminUserLoginNotificationEmailManagerInterface $emailManager,
        protected SessionFingerprintGeneratorInterface $fingerprintGenerator,
        protected UserAgentParserInterface $userAgentParser,
        protected GeoIpLookupInterface $geoIpLookup,
        protected ClockInterface $clock,
        protected bool $sessionTrackingEnabled,
        protected bool $loginNotificationsEnabled,
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if (!$this->sessionTrackingEnabled && !$this->loginNotificationsEnabled) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof AdminUserInterface) {
            return;
        }

        $request = $event->getRequest();
        $userAgent = $request->headers->get('User-Agent');
        $ipAddress = $request->getClientIp();

        if ($this->sessionTrackingEnabled) {
            $sessionId = $this->extractSessionId($request);
            if ($sessionId !== null) {
                $this->tracker->track($user, $sessionId, $userAgent, $ipAddress);
            }
        }

        if ($this->loginNotificationsEnabled) {
            $fingerprint = $this->fingerprintGenerator->generate($userAgent, $ipAddress);
            $isNewDevice = $this->newDeviceDetector->checkAndRemember($user, $fingerprint);
            if ($isNewDevice) {
                $geo = $this->geoIpLookup->lookup($ipAddress);
                $this->emailManager->sendNewDeviceNotification(
                    $user,
                    $this->clock->now(),
                    $ipAddress,
                    $geo?->countryCode,
                    $geo?->city,
                    $this->userAgentParser->parse($userAgent),
                );
            }
        }
    }

    protected function extractSessionId(Request $request): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }
        $sessionId = $request->getSession()->getId();

        return $sessionId === '' ? null : $sessionId;
    }
}
