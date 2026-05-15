<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\CustomerLoginNotificationEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerNewDeviceDetectorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\GeoIpLookupInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\SessionFingerprintGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\UserAgentParserInterface;

class CustomerSessionLoginListener implements CustomerSessionLoginListenerInterface
{
    public function __construct(
        protected CustomerSessionTrackerInterface $tracker,
        protected CustomerNewDeviceDetectorInterface $newDeviceDetector,
        protected CustomerLoginNotificationEmailManagerInterface $emailManager,
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
        $user = $event->getUser();
        if (!$user instanceof ShopUserInterface) {
            return;
        }

        $this->handleLogin($user, $event->getRequest());
    }

    /**
     * Same tracking + new-device-notification logic that runs after a regular
     * password login via `LoginSuccessEvent`, exposed so non-Symfony-authenticator
     * login flows (OAuth callback, magic link, passkey) can invoke it directly
     * after their own manual `tokenStorage->setToken()` — those flows don't
     * dispatch `LoginSuccessEvent`, so without this hook the session list and
     * new-device email would silently skip them.
     */
    public function handleLogin(ShopUserInterface $user, Request $request): void
    {
        if (!$this->sessionTrackingEnabled && !$this->loginNotificationsEnabled) {
            return;
        }

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
