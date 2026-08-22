<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use ThreeBRS\EnterpriseSecurityBundle\Session\GeoIp\GeoIpLookupInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\SessionFingerprintGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentParserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\CustomerLoginNotificationEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ScopedFeatureCheckerInterface;

/**
 * Tracks the active session row + emits a new-device email after a customer
 * successfully signs in. Invoked from `CustomerSessionLoginListener` after the
 * standard password `LoginSuccessEvent`, and directly from
 * `OAuthCallbackController` whose manual `tokenStorage->setToken()` bypasses
 * the firewall event dispatcher.
 */
class CustomerSessionLoginHandler implements CustomerSessionLoginHandlerInterface
{
    public function __construct(
        protected CustomerSessionTrackerInterface $tracker,
        protected CustomerNewDeviceDetectorInterface $newDeviceDetector,
        protected CustomerLoginNotificationEmailManagerInterface $emailManager,
        protected SessionFingerprintGeneratorInterface $fingerprintGenerator,
        protected UserAgentParserInterface $userAgentParser,
        protected GeoIpLookupInterface $geoIpLookup,
        protected ClockInterface $clock,
        protected ScopedFeatureCheckerInterface $sessionManagement,
        protected ScopedFeatureCheckerInterface $loginNotifications,
    ) {
    }

    public function handle(ShopUserInterface $user, Request $request): void
    {
        if (!$this->sessionManagement->isEnabled(SettingsScope::CUSTOMER) &&
            !$this->loginNotifications->isEnabled(SettingsScope::CUSTOMER)) {
            return;
        }

        $userAgent = $request->headers->get('User-Agent');
        $ipAddress = $request->getClientIp();

        if ($this->sessionManagement->isEnabled(SettingsScope::CUSTOMER)) {
            $sessionId = $this->extractSessionId($request);
            if ($sessionId !== null) {
                $this->tracker->track($user, $sessionId, $userAgent, $ipAddress);
            }
        }

        if ($this->loginNotifications->isEnabled(SettingsScope::CUSTOMER)) {
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
