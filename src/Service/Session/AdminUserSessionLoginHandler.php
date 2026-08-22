<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\GeoIpLookupInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\SessionFingerprintGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentParserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AdminUserLoginNotificationEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ScopedFeatureCheckerInterface;

/**
 * Tracks the active session row + emits a new-device email after an admin
 * successfully signs in. Invoked from `AdminUserSessionLoginListener` after the
 * standard password `LoginSuccessEvent`, and directly from
 * `OAuthCallbackController` whose manual `tokenStorage->setToken()` bypasses
 * the firewall event dispatcher.
 */
class AdminUserSessionLoginHandler implements AdminUserSessionLoginHandlerInterface
{
    public function __construct(
        protected AdminUserSessionTrackerInterface $tracker,
        protected AdminUserNewDeviceDetectorInterface $newDeviceDetector,
        protected AdminUserLoginNotificationEmailManagerInterface $emailManager,
        protected SessionFingerprintGeneratorInterface $fingerprintGenerator,
        protected UserAgentParserInterface $userAgentParser,
        protected GeoIpLookupInterface $geoIpLookup,
        protected ClockInterface $clock,
        protected ScopedFeatureCheckerInterface $sessionManagement,
        protected ScopedFeatureCheckerInterface $loginNotifications,
    ) {
    }

    public function handle(AdminUserInterface $user, Request $request): void
    {
        if (!$this->sessionManagement->isEnabled(SettingsScope::ADMIN) &&
            !$this->loginNotifications->isEnabled(SettingsScope::ADMIN)) {
            return;
        }

        $userAgent = $request->headers->get('User-Agent');
        $ipAddress = $request->getClientIp();

        if ($this->sessionManagement->isEnabled(SettingsScope::ADMIN)) {
            $sessionId = $this->extractSessionId($request);
            if ($sessionId !== null) {
                $this->tracker->track($user, $sessionId, $userAgent, $ipAddress);
            }
        }

        if ($this->loginNotifications->isEnabled(SettingsScope::ADMIN)) {
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
