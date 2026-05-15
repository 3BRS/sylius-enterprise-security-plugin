<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\SecuritySettings;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\AccountLockoutSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\MagicLinkSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\OAuthAdminPolicySettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\OAuthSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasskeySettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasswordExpirationSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasswordHistorySettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasswordPolicySettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\RateLimitSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\SimpleToggleSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\TwoFactorSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\Exception\ConcurrentSettingsWriteException;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsWriterInterface;

class SaveTabController implements SaveTabControllerInterface
{
    use FlashHelperTrait;

    /**
     * Map of tab name => form type, settings prefix, form options, and an optional
     * key_map to translate flat form-field names into nested settings paths
     * (Symfony form-field names cannot contain dots, but settings paths use them).
     *
     * `options_per_scope` lets a tab inject scope-specific options into the form
     * (e.g. rate_limit has a different action set for customer vs admin); they
     * are merged on top of `options` at request time.
     *
     * `scopes` (when present) restricts the tab to a subset of scopes — used for
     * tabs that only make sense in one scope (e.g. oauth admin policy).
     *
     * @var array<string, array{type: class-string<FormTypeInterface<array<string, mixed>>>, prefix: string, options: array<string, mixed>, key_map?: array<string, string>, options_per_scope?: array<string, array<string, mixed>>, scopes?: list<SettingsScope>}>
     */
    protected array $tabs;

    public function __construct(
        protected SettingsWriterInterface $writer,
        protected FormFactoryInterface $formFactory,
        protected RouterInterface $router,
    ) {
        $rateLimitKeyMap = [];
        foreach (['login', 'password_reset', 'register', 'magic_link'] as $action) {
            $rateLimitKeyMap[$action . '_enabled'] = $action . '.enabled';
            $rateLimitKeyMap[$action . '_limit'] = $action . '.limit';
            $rateLimitKeyMap[$action . '_interval'] = $action . '.interval';
        }

        $this->tabs = [
            'password_policy' => ['type' => PasswordPolicySettingsType::class, 'prefix' => 'password_policy', 'options' => []],
            'password_history' => ['type' => PasswordHistorySettingsType::class, 'prefix' => 'password_history', 'options' => []],
            'password_expiration' => ['type' => PasswordExpirationSettingsType::class, 'prefix' => 'password_expiration', 'options' => []],
            'password_change_notification' => [
                'type' => SimpleToggleSettingsType::class,
                'prefix' => 'password_change_notification',
                'options' => ['label' => 'three_brs.ui.security_settings.password_change_notification.enabled'],
            ],
            'two_factor_authentication' => [
                'type' => TwoFactorSettingsType::class,
                'prefix' => 'two_factor_authentication',
                'options' => [],
                'key_map' => [
                    'recovery_codes_enabled' => 'recovery_codes.enabled',
                    'recovery_codes_count' => 'recovery_codes.count',
                ],
            ],
            'magic_link' => ['type' => MagicLinkSettingsType::class, 'prefix' => 'magic_link', 'options' => []],
            'passkey' => ['type' => PasskeySettingsType::class, 'prefix' => 'passkey', 'options' => []],
            'account_lockout' => ['type' => AccountLockoutSettingsType::class, 'prefix' => 'account_lockout', 'options' => []],
            'rate_limit' => [
                'type' => RateLimitSettingsType::class,
                'prefix' => 'rate_limit',
                'options' => [],
                'key_map' => $rateLimitKeyMap,
                'options_per_scope' => [
                    SettingsScope::CUSTOMER->value => ['actions' => ['login', 'password_reset', 'register', 'magic_link']],
                    SettingsScope::ADMIN->value => ['actions' => ['login', 'password_reset', 'magic_link']],
                ],
            ],
            'session_management' => [
                'type' => SimpleToggleSettingsType::class,
                'prefix' => 'session_management',
                'options' => ['label' => 'three_brs.ui.security_settings.session_management.enabled'],
            ],
            'login_notifications' => [
                'type' => SimpleToggleSettingsType::class,
                'prefix' => 'login_notifications',
                'options' => ['label' => 'three_brs.ui.security_settings.login_notifications.enabled'],
            ],
            'oauth' => [
                'type' => OAuthSettingsType::class,
                'prefix' => 'oauth',
                'options' => [],
                'key_map' => [
                    'google_enabled' => 'google.enabled',
                    'apple_enabled' => 'apple.enabled',
                ],
            ],
            'oauth_admin_policy' => [
                'type' => OAuthAdminPolicySettingsType::class,
                'prefix' => 'oauth',
                'options' => [],
                'scopes' => [SettingsScope::ADMIN],
            ],
        ];
    }

    public function __invoke(Request $request, string $tab): Response
    {
        if (!array_key_exists($tab, $this->tabs)) {
            throw new NotFoundHttpException(sprintf('Unknown security settings tab "%s".', $tab));
        }

        $scopeParam = $request->query->getString('scope', SettingsScope::CUSTOMER->value);
        $scope = SettingsScope::tryFrom($scopeParam) ?? SettingsScope::CUSTOMER;

        $tabConfig = $this->tabs[$tab];
        if (isset($tabConfig['scopes']) && !in_array($scope, $tabConfig['scopes'], true)) {
            throw new NotFoundHttpException(sprintf('Tab "%s" is not available for scope "%s".', $tab, $scope->value));
        }

        $tabOptions = $tabConfig['options'];
        if (isset($tabConfig['options_per_scope'][$scope->value])) {
            $tabOptions = array_merge($tabOptions, $tabConfig['options_per_scope'][$scope->value]);
        }

        $form = $this->formFactory->create($tabConfig['type'], null, $tabOptions);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->flashFormErrors($request, $form);

            return $this->redirect($scope, $tab);
        }

        $data = $form->getData();
        if (!is_array($data)) {
            throw new \LogicException(sprintf('Form "%s" returned non-array data.', $tabConfig['type']));
        }

        $values = [];
        $keyMap = $tabConfig['key_map'] ?? [];
        foreach ($data as $key => $value) {
            $mappedKey = $keyMap[$key] ?? $key;
            $values[$tabConfig['prefix'] . '.' . $mappedKey] = $value;
        }

        $this->writer->setMany($scope, $values);

        try {
            $this->writer->flush();
        } catch (ConcurrentSettingsWriteException) {
            $this->addFlashMessage($request, 'error', 'three_brs.security_settings.concurrent_conflict');

            return $this->redirect($scope, $tab);
        }

        $this->addFlashMessage($request, 'success', 'three_brs.security_settings.saved');

        return $this->redirect($scope, $tab);
    }

    /**
     * @param FormInterface<array<string, mixed>|null> $form
     */
    protected function flashFormErrors(Request $request, FormInterface $form): void
    {
        // Generic key so the admin always sees something (e.g. on a bare CSRF
        // failure with no field errors), plus one flash per field error so the
        // admin can fix the offending field directly. Symfony's form pipeline
        // already translates constraint messages, so we surface them verbatim.
        $this->addFlashMessage($request, 'error', 'three_brs.security_settings.invalid');

        foreach ($form->getErrors(true) as $error) {
            $field = $error->getOrigin()?->getName() ?? '?';
            $this->addFlashMessage(
                $request,
                'error',
                sprintf('%s: %s', $field, $error->getMessage()),
            );
        }
    }

    protected function redirect(SettingsScope $scope, string $tab): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('three_brs_admin_security_settings_index', [
            'scope' => $scope->value,
            'tab' => $tab,
        ]));
    }
}
