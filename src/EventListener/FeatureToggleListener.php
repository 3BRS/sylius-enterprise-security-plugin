<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

/**
 * Security Settings offers a switch for passkeys, magic link, session management,
 * account lockout and account deletion, and the pages behind them were reading a
 * container parameter fixed when the container was built. An administrator could
 * turn a feature off, watch the menu entry disappear, and still reach every
 * endpoint by its URL — the switch looked like it worked and did not.
 *
 * The check lives here rather than in each controller because three of them are
 * the bundle's own concrete classes, which the plugin wires but does not own, and
 * because a page and the actions posted from it have to agree.
 *
 * This narrows: the parameter still gates the same endpoints and is what decides
 * whether a feature was ever configured, so a feature left off in the
 * configuration file cannot be switched on from the database. That direction is
 * not wanted anyway — passkeys need a relying-party id and name, and the
 * extension refuses to build a container that enables them without one.
 */
class FeatureToggleListener implements FeatureToggleListenerInterface
{
    /**
     * Route name => [settings path, scope]. The scope is the group whose switch
     * governs the endpoint, which for the administration screens of a customer
     * feature is the customer one — the administrator is looking at customer data,
     * not at their own.
     *
     * @var array<string, array{string, SettingsScope}>
     */
    protected const ROUTE_MAP = [
        'three_brs_shop_passkey_index' => ['passkey', SettingsScope::CUSTOMER],
        'three_brs_shop_passkey_delete' => ['passkey', SettingsScope::CUSTOMER],
        'three_brs_shop_passkey_register_options' => ['passkey', SettingsScope::CUSTOMER],
        'three_brs_shop_passkey_register_verify' => ['passkey', SettingsScope::CUSTOMER],
        'three_brs_shop_passkey_login_options' => ['passkey', SettingsScope::CUSTOMER],
        'three_brs_shop_passkey_login_verify' => ['passkey', SettingsScope::CUSTOMER],
        'three_brs_admin_passkey_index' => ['passkey', SettingsScope::ADMIN],
        'three_brs_admin_passkey_delete' => ['passkey', SettingsScope::ADMIN],
        'three_brs_admin_passkey_register_options' => ['passkey', SettingsScope::ADMIN],
        'three_brs_admin_passkey_register_verify' => ['passkey', SettingsScope::ADMIN],
        'three_brs_admin_passkey_login_options' => ['passkey', SettingsScope::ADMIN],
        'three_brs_admin_passkey_login_verify' => ['passkey', SettingsScope::ADMIN],

        'three_brs_shop_magic_link_request' => ['magic_link', SettingsScope::CUSTOMER],
        'three_brs_shop_magic_link_verify' => ['magic_link', SettingsScope::CUSTOMER],
        'three_brs_admin_magic_link_request' => ['magic_link', SettingsScope::ADMIN],
        'three_brs_admin_magic_link_verify' => ['magic_link', SettingsScope::ADMIN],

        'three_brs_shop_sessions' => ['session_management', SettingsScope::CUSTOMER],
        'three_brs_shop_sessions_revoke_others' => ['session_management', SettingsScope::CUSTOMER],
        'three_brs_shop_session_revoke' => ['session_management', SettingsScope::CUSTOMER],
        'three_brs_admin_sessions' => ['session_management', SettingsScope::ADMIN],
        'three_brs_admin_sessions_revoke_others' => ['session_management', SettingsScope::ADMIN],
        'three_brs_admin_session_revoke' => ['session_management', SettingsScope::ADMIN],
        // Revoking a customer's sessions from the admin panel reads the same rows the
        // customer's own list does, so it answers to the customer switch.
        'three_brs_admin_customer_revoke_all_sessions' => ['session_management', SettingsScope::CUSTOMER],
        'three_brs_admin_customer_revoke_session' => ['session_management', SettingsScope::CUSTOMER],

        'three_brs_admin_locked_customers' => ['account_lockout', SettingsScope::CUSTOMER],
        'three_brs_admin_locked_customer_unlock' => ['account_lockout', SettingsScope::CUSTOMER],
        'three_brs_admin_locked_admins' => ['account_lockout', SettingsScope::ADMIN],
        'three_brs_admin_locked_admin_unlock' => ['account_lockout', SettingsScope::ADMIN],

        'three_brs_shop_account_deletion_request' => ['account_deletion', SettingsScope::CUSTOMER],
        'three_brs_admin_account_deletions' => ['account_deletion', SettingsScope::CUSTOMER],
        'three_brs_admin_account_deletion_cancel' => ['account_deletion', SettingsScope::CUSTOMER],
    ];

    public function __construct(
        protected FeatureToggleInterface $featureToggle,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (!is_string($route)) {
            return;
        }

        $gate = static::ROUTE_MAP[$route] ?? null;
        if ($gate === null) {
            return;
        }

        [$feature, $scope] = $gate;

        if (!$this->featureToggle->isEnabled($feature, $scope)) {
            // The same answer the controllers give for a feature left out of the
            // configuration, so a switched-off feature is indistinguishable from one
            // that was never installed.
            throw new NotFoundHttpException();
        }
    }
}
