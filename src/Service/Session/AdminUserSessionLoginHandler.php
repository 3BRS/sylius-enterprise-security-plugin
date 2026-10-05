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
 * Records the session and sends the new-device email after an admin signs in. Called from
 * `AdminUserSessionLoginListener` on every `LoginSuccessEvent` (with two-factor authentication after the
 * password and again after the authenticator code), and directly from the controllers that sign in
 * through `tokenStorage->setToken()` outside the firewall: OAuth callback, OAuth link confirmation,
 * passkey and magic link.
 */
class AdminUserSessionLoginHandler implements AdminUserSessionLoginHandlerInterface
{
    public const TRACKED_SESSION_ID_ATTRIBUTE = '_three_brs_admin_tracked_session_id';

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
                $session = $request->getSession();
                // When a sign-in gives the session a new ID, the session recorded under the
                // previous ID moves to it.
                $trackedSessionId = $session->get(static::TRACKED_SESSION_ID_ATTRIBUTE);
                if (is_string($trackedSessionId) && $trackedSessionId !== $sessionId) {
                    $this->tracker->moveSession($trackedSessionId, $sessionId, $user);
                }
                $this->tracker->track($user, $sessionId, $userAgent, $ipAddress);
                $session->set(static::TRACKED_SESSION_ID_ATTRIBUTE, $sessionId);
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
