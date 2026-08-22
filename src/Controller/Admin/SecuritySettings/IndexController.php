<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\SecuritySettings;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\SecuritySettingsType;
use Twig\Environment;

#[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
class IndexController implements IndexControllerInterface
{
    public function __construct(
        protected SettingsProviderInterface $settings,
        protected FormFactoryInterface $formFactory,
        protected Environment $twig,
        protected RouterInterface $router,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $scopeParam = $request->query->getString('scope', SettingsScope::CUSTOMER->value);
        $scope = SettingsScope::tryFrom($scopeParam) ?? SettingsScope::CUSTOMER;

        $form = $this->formFactory->create(SecuritySettingsType::class, $this->loadData($scope), [
            'scope' => $scope,
            'action' => $this->router->generate('three_brs_admin_security_settings_save', [
                'scope' => $scope->value,
            ]),
        ]);

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/SecuritySettings/index.html.twig',
            [
                'scope_value' => $scope->value,
                'form' => $form->createView(),
            ],
        ));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function loadData(SettingsScope $scope): array
    {
        $data = [
            'password_policy' => [
                'min_length' => $this->settings->getInt('password_policy.min_length', $scope),
                'max_length' => $this->settings->getNullableInt('password_policy.max_length', $scope),
                'require_uppercase' => $this->settings->getBool('password_policy.require_uppercase', $scope),
                'require_lowercase' => $this->settings->getBool('password_policy.require_lowercase', $scope),
                'require_numbers' => $this->settings->getBool('password_policy.require_numbers', $scope),
                'require_special_characters' => $this->settings->getBool('password_policy.require_special_characters', $scope),
            ],
            'password_history' => [
                'enabled' => $this->settings->getBool('password_history.enabled', $scope),
                'count' => $this->settings->getInt('password_history.count', $scope),
            ],
            'password_expiration' => [
                'enabled' => $this->settings->getBool('password_expiration.enabled', $scope),
                'days' => $this->settings->getInt('password_expiration.days', $scope),
            ],
            'password_change_notification' => [
                'enabled' => $this->settings->getBool('password_change_notification.enabled', $scope),
            ],
            'two_factor_authentication' => [
                'mode' => $this->settings->getString('two_factor_authentication.mode', $scope),
                'recovery_codes_enabled' => $this->settings->getBool('two_factor_authentication.recovery_codes.enabled', $scope),
                'recovery_codes_count' => $this->settings->getInt('two_factor_authentication.recovery_codes.count', $scope),
            ],
            'magic_link' => [
                'enabled' => $this->settings->getBool('magic_link.enabled', $scope),
                'expiration_seconds' => $this->settings->getInt('magic_link.expiration_seconds', $scope),
            ],
            'passkey' => [
                'enabled' => $this->settings->getBool('passkey.enabled', $scope),
            ],
            'account_lockout' => [
                'enabled' => $this->settings->getBool('account_lockout.enabled', $scope),
                'max_attempts' => $this->settings->getInt('account_lockout.max_attempts', $scope),
                'auto_unlock_after' => $this->settings->getNullableInt('account_lockout.auto_unlock_after', $scope),
            ],
            'session_management' => [
                'enabled' => $this->settings->getBool('session_management.enabled', $scope),
            ],
            'login_notifications' => [
                'enabled' => $this->settings->getBool('login_notifications.enabled', $scope),
            ],
            'password_login' => [
                'enabled' => $this->settings->getBool('password_login.enabled', $scope),
            ],
            'oauth' => [
                'google_enabled' => $this->settings->getBool('oauth.google.enabled', $scope),
                'apple_enabled' => $this->settings->getBool('oauth.apple.enabled', $scope),
                'microsoft_enabled' => $this->settings->getBool('oauth.microsoft.enabled', $scope),
            ],
        ];

        $rateLimitActions = $scope === SettingsScope::CUSTOMER
            ? ['login', 'password_reset', 'register', 'magic_link']
            : ['login', 'password_reset', 'magic_link'];
        $rateLimitData = [];
        foreach ($rateLimitActions as $action) {
            $rateLimitData[$action . '_enabled'] = $this->settings->getBool('rate_limit.' . $action . '.enabled', $scope);
            $rateLimitData[$action . '_limit'] = $this->settings->getInt('rate_limit.' . $action . '.limit', $scope);
            $rateLimitData[$action . '_interval'] = $this->settings->getString('rate_limit.' . $action . '.interval', $scope);
        }
        $data['rate_limit'] = $rateLimitData;

        if ($scope === SettingsScope::ADMIN) {
            $whitelist = $this->settings->get('oauth.auto_register_allowed_email_domains', $scope);
            $data['oauth_admin_policy'] = [
                'default_locale' => $this->settings->getString('oauth.default_locale', $scope),
                'auto_register_allowed_email_domains' => is_array($whitelist) ? $whitelist : [],
            ];

            $globalCidrsValue = $this->settings->get('ip_whitelist.global_cidrs', $scope);
            $data['ip_whitelist'] = [
                'enabled' => $this->settings->getBool('ip_whitelist.enabled', $scope),
                'global_cidrs' => is_array($globalCidrsValue) ? $globalCidrsValue : [],
            ];

            $blacklistCidrsValue = $this->settings->get('ip_blacklist.global_cidrs', $scope);
            $data['ip_blacklist'] = [
                'enabled' => $this->settings->getBool('ip_blacklist.enabled', $scope),
                'global_cidrs' => is_array($blacklistCidrsValue) ? $blacklistCidrsValue : [],
            ];
        }

        if ($scope === SettingsScope::CUSTOMER) {
            $customerWhitelist = $this->settings->get('oauth.auto_register_allowed_email_domains', $scope);
            $data['oauth_customer_policy'] = [
                'auto_register_allowed_email_domains' => is_array($customerWhitelist) ? $customerWhitelist : [],
            ];

            $data['account_deletion'] = [
                'enabled' => $this->settings->getBool('account_deletion.enabled', $scope),
                'grace_period_days' => $this->settings->getInt('account_deletion.grace_period_days', $scope),
            ];
        }

        return $data;
    }
}
