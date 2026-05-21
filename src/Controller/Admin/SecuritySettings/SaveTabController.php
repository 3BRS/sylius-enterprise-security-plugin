<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\SecuritySettings;

use LogicException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\FlashHelperTrait;
use ThreeBRS\EnterpriseSecurityBundle\Settings\Exception\ConcurrentSettingsWriteException;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\SecuritySettingsType;

class SaveTabController implements SaveTabControllerInterface
{
    use FlashHelperTrait;

    public function __construct(
        protected SettingsWriterInterface $writer,
        protected FormFactoryInterface $formFactory,
        protected RouterInterface $router,
        protected TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $scopeParam = $request->query->getString('scope', SettingsScope::CUSTOMER->value);
        $scope = SettingsScope::tryFrom($scopeParam) ?? SettingsScope::CUSTOMER;

        $form = $this->formFactory->create(SecuritySettingsType::class, null, ['scope' => $scope]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->flashFormErrors($request, $form);

            return $this->redirect($scope);
        }

        $data = $form->getData();
        if (!is_array($data)) {
            throw new LogicException('Security settings form returned non-array data.');
        }

        $values = [];
        foreach ($data as $tab => $tabData) {
            if (!is_array($tabData)) {
                continue;
            }
            $prefix = $this->prefixForTab($tab);
            $keyMap = $this->keyMapForTab($tab);
            foreach ($tabData as $key => $value) {
                $mappedKey = $keyMap[$key] ?? $key;
                $values[$prefix . '.' . $mappedKey] = $value;
            }
        }

        $this->writer->setMany($scope, $values);

        try {
            $this->writer->flush();
        } catch (ConcurrentSettingsWriteException) {
            $this->addFlashMessage($request, 'error', 'three_brs.security_settings.concurrent_conflict');

            return $this->redirect($scope);
        }

        $this->addFlashMessage($request, 'success', 'three_brs.security_settings.saved');

        return $this->redirect($scope);
    }

    protected function prefixForTab(string $tab): string
    {
        // oauth_{admin,customer}_policy fields share the oauth.* prefix in the
        // settings store — the tab key is just a separate UI section, not a
        // separate settings namespace.
        if ($tab === 'oauth_admin_policy' || $tab === 'oauth_customer_policy') {
            return 'oauth';
        }

        return $tab;
    }

    /**
     * @return array<string, string>
     */
    protected function keyMapForTab(string $tab): array
    {
        // Symfony form-field names cannot contain dots, so flat field names like
        // `recovery_codes_enabled` are translated to nested settings paths like
        // `recovery_codes.enabled` before writing.
        if ($tab === 'two_factor_authentication') {
            return [
                'recovery_codes_enabled' => 'recovery_codes.enabled',
                'recovery_codes_count' => 'recovery_codes.count',
            ];
        }

        if ($tab === 'rate_limit') {
            $map = [];
            foreach (['login', 'password_reset', 'register', 'magic_link'] as $action) {
                $map[$action . '_enabled'] = $action . '.enabled';
                $map[$action . '_limit'] = $action . '.limit';
                $map[$action . '_interval'] = $action . '.interval';
            }

            return $map;
        }

        if ($tab === 'oauth') {
            return [
                'google_enabled' => 'google.enabled',
                'apple_enabled' => 'apple.enabled',
            ];
        }

        return [];
    }

    /**
     * @param FormInterface<array<string, mixed>|null> $form
     */
    protected function flashFormErrors(Request $request, FormInterface $form): void
    {
        // Root-level errors (deep: false) are almost always CSRF on this form —
        // Symfony's CsrfValidationListener calls `$form->addError()` on the
        // root, never on a child field. Customer / admin sign-ins elsewhere in
        // the browser invalidate the pre-auth CSRF token storage by design
        // (Symfony SessionAuthenticationStrategy::MIGRATE destroys the old
        // session), so the admin's still-open settings tab fails validation.
        // Show a targeted "session renewed, refresh & retry" flash instead of
        // the generic `_token: The CSRF token is invalid` echo from Symfony.
        if (count($form->getErrors(deep: false)) > 0) {
            $this->addFlashMessage($request, 'error', 'three_brs.security_settings.session_expired');

            return;
        }

        $this->addFlashMessage($request, 'error', 'three_brs.security_settings.invalid');

        // Form error messages are translation keys (Symfony's default — both
        // constraint violations and our manual `addError(new FormError($key))`
        // calls pass the raw key). The Sylius flash partial trans-filters the
        // whole flash string with the `flashes` domain only, so a raw key from
        // `validators` would render literally. Pre-translate here via the
        // `validators` domain (Symfony's default for form errors) and emit the
        // already-human-readable string — translation messages name the field
        // explicitly when relevant, so we don't prefix with the field name.
        foreach ($form->getErrors(true) as $error) {
            $message = $this->translator->trans(
                $error->getMessage(),
                $error->getMessageParameters(),
                'validators',
            );
            $this->addFlashMessage($request, 'error', $message);
        }
    }

    protected function redirect(SettingsScope $scope): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('three_brs_admin_security_settings_index', [
            'scope' => $scope->value,
        ]));
    }
}
