<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\SecuritySettings;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\AccountLockoutSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\MagicLinkSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasskeySettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasswordExpirationSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasswordHistorySettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasswordPolicySettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\SimpleToggleSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\TwoFactorSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;
use Twig\Environment;

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

        $forms = $this->buildForms($scope);
        $rendered = [];
        foreach ($forms as $tab => $form) {
            $rendered[$tab] = $form->createView();
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/SecuritySettings/index.html.twig',
            [
                'scope' => $scope,
                'scope_value' => $scope->value,
                'forms' => $rendered,
            ],
        ));
    }

    /**
     * @return array<string, \Symfony\Component\Form\FormInterface<array<string, mixed>>>
     */
    protected function buildForms(SettingsScope $scope): array
    {
        $forms = [];

        if ($scope === SettingsScope::CUSTOMER || $scope === SettingsScope::ADMIN) {
            $forms['password_policy'] = $this->formFactory->create(PasswordPolicySettingsType::class, [
                'min_length' => $this->settings->getInt('password_policy.min_length', $scope),
                'max_length' => $this->settings->getNullableInt('password_policy.max_length', $scope),
                'require_uppercase' => $this->settings->getBool('password_policy.require_uppercase', $scope),
                'require_lowercase' => $this->settings->getBool('password_policy.require_lowercase', $scope),
                'require_numbers' => $this->settings->getBool('password_policy.require_numbers', $scope),
                'require_special_characters' => $this->settings->getBool('password_policy.require_special_characters', $scope),
            ], ['action' => $this->saveUrl('password_policy', $scope)]);

            $forms['password_history'] = $this->formFactory->create(PasswordHistorySettingsType::class, [
                'enabled' => $this->settings->getBool('password_history.enabled', $scope),
                'count' => $this->settings->getInt('password_history.count', $scope),
            ], ['action' => $this->saveUrl('password_history', $scope)]);

            $forms['password_expiration'] = $this->formFactory->create(PasswordExpirationSettingsType::class, [
                'enabled' => $this->settings->getBool('password_expiration.enabled', $scope),
                'days' => $this->settings->getInt('password_expiration.days', $scope),
            ], ['action' => $this->saveUrl('password_expiration', $scope)]);

            $forms['password_change_notification'] = $this->formFactory->create(SimpleToggleSettingsType::class, [
                'enabled' => $this->settings->getBool('password_change_notification.enabled', $scope),
            ], [
                'label' => 'three_brs.ui.security_settings.password_change_notification.enabled',
                'action' => $this->saveUrl('password_change_notification', $scope),
            ]);

            $forms['two_factor_authentication'] = $this->formFactory->create(TwoFactorSettingsType::class, [
                'mode' => $this->settings->getString('two_factor_authentication.mode', $scope),
                'recovery_codes_enabled' => $this->settings->getBool('two_factor_authentication.recovery_codes.enabled', $scope),
                'recovery_codes_count' => $this->settings->getInt('two_factor_authentication.recovery_codes.count', $scope),
            ], ['action' => $this->saveUrl('two_factor_authentication', $scope)]);

            $forms['magic_link'] = $this->formFactory->create(MagicLinkSettingsType::class, [
                'enabled' => $this->settings->getBool('magic_link.enabled', $scope),
                'expiration_seconds' => $this->settings->getInt('magic_link.expiration_seconds', $scope),
            ], ['action' => $this->saveUrl('magic_link', $scope)]);

            $forms['passkey'] = $this->formFactory->create(PasskeySettingsType::class, [
                'enabled' => $this->settings->getBool('passkey.enabled', $scope),
            ], ['action' => $this->saveUrl('passkey', $scope)]);

            $forms['account_lockout'] = $this->formFactory->create(AccountLockoutSettingsType::class, [
                'enabled' => $this->settings->getBool('account_lockout.enabled', $scope),
                'max_attempts' => $this->settings->getInt('account_lockout.max_attempts', $scope),
                'auto_unlock_after' => $this->settings->getNullableInt('account_lockout.auto_unlock_after', $scope),
            ], ['action' => $this->saveUrl('account_lockout', $scope)]);

            $forms['session_management'] = $this->formFactory->create(SimpleToggleSettingsType::class, [
                'enabled' => $this->settings->getBool('session_management.enabled', $scope),
            ], [
                'label' => 'three_brs.ui.security_settings.session_management.enabled',
                'action' => $this->saveUrl('session_management', $scope),
            ]);

            $forms['login_notifications'] = $this->formFactory->create(SimpleToggleSettingsType::class, [
                'enabled' => $this->settings->getBool('login_notifications.enabled', $scope),
            ], [
                'label' => 'three_brs.ui.security_settings.login_notifications.enabled',
                'action' => $this->saveUrl('login_notifications', $scope),
            ]);
        }

        return $forms;
    }

    protected function saveUrl(string $tab, SettingsScope $scope): string
    {
        return $this->router->generate('three_brs_admin_security_settings_save', [
            'tab' => $tab,
            'scope' => $scope->value,
        ]);
    }
}
