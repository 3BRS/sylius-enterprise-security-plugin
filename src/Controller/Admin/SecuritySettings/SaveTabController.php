<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\SecuritySettings;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\AccountLockoutSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\MagicLinkSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasskeySettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasswordExpirationSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasswordHistorySettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\PasswordPolicySettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\SimpleToggleSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\TwoFactorSettingsType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsWriterInterface;

class SaveTabController implements SaveTabControllerInterface
{
    /**
     * Map of tab name => [form_type_class, prefix, options].
     *
     * @var array<string, array{type: class-string<\Symfony\Component\Form\FormTypeInterface<array<string, mixed>>>, prefix: string, options: array<string, mixed>}>
     */
    protected array $tabs;

    public function __construct(
        protected SettingsWriterInterface $writer,
        protected FormFactoryInterface $formFactory,
        protected RouterInterface $router,
        protected TranslatorInterface $translator,
    ) {
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
            ],
            'magic_link' => ['type' => MagicLinkSettingsType::class, 'prefix' => 'magic_link', 'options' => []],
            'passkey' => ['type' => PasskeySettingsType::class, 'prefix' => 'passkey', 'options' => []],
            'account_lockout' => ['type' => AccountLockoutSettingsType::class, 'prefix' => 'account_lockout', 'options' => []],
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
        $form = $this->formFactory->create($tabConfig['type'], null, $tabConfig['options']);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash($request, 'error', 'three_brs.ui.security_settings.flash.invalid');

            return $this->redirect($scope, $tab);
        }

        $data = $form->getData();
        if (!is_array($data)) {
            throw new NotFoundHttpException();
        }

        $values = [];
        foreach ($data as $key => $value) {
            $values[$tabConfig['prefix'] . '.' . $key] = $value;
        }

        $this->writer->setMany($scope, $values);
        $this->writer->flush();

        $this->addFlash($request, 'success', 'three_brs.ui.security_settings.flash.saved');

        return $this->redirect($scope, $tab);
    }

    protected function redirect(SettingsScope $scope, string $tab): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('three_brs_admin_security_settings_index', [
            'scope' => $scope->value,
            'tab' => $tab,
        ]));
    }

    protected function addFlash(Request $request, string $type, string $messageKey): void
    {
        $session = $request->getSession();
        if (!method_exists($session, 'getFlashBag')) {
            return;
        }
        $session->getFlashBag()->add($type, $this->translator->trans($messageKey));
    }
}
