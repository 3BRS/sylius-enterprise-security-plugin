<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\AdminUserType;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordLoginControlAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

class AdminUserTypeExtension extends AbstractTypeExtension implements AdminUserTypeExtensionInterface
{
    public function __construct(
        protected LastAuthMethodGuardInterface $guard,
        protected FeatureToggleInterface $featureToggle,
        protected TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dataClass = $options['data_class'] ?? null;
        if ($dataClass === null || !is_a($dataClass, PasswordExpirationAdminUserInterface::class, true)) {
            return;
        }

        $builder->add('forcePasswordChange', CheckboxType::class, [
            'label' => 'three_brs.form.admin_user.force_password_change',
            'required' => false,
        ]);

        // Only expose the switch when the feature is enabled for the admin scope —
        // when it is off the preference is never enforced (see
        // AdminUserPasswordLoginCheckListener), so the checkbox would be a no-op.
        if (!$this->featureToggle->isEnabled('password_login_control', SettingsScope::ADMIN)) {
            return;
        }

        // Maps directly to the admin user's passwordLoginAllowed flag
        // (PasswordLoginControlAdminUserTrait), so it is loaded + persisted by the
        // standard Sylius admin-user flow and defaults to "allowed" (checked).
        $builder->add('passwordLoginAllowed', CheckboxType::class, [
            'label' => 'three_brs.form.admin_user.password_login_allowed',
            'help' => 'three_brs.form.admin_user.password_login_allowed_help',
            'required' => false,
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->guardAgainstLockout(...));
    }

    public function guardAgainstLockout(FormEvent $event): void
    {
        $admin = $event->getData();
        $form = $event->getForm();
        if (!$admin instanceof AdminUserInterface ||
            !$admin instanceof PasswordLoginControlAdminUserInterface ||
            !$form->has('passwordLoginAllowed')) {
            return;
        }

        // The checkbox has already been mapped onto the entity at this point; if it
        // would lock the admin out (no other usable sign-in method), flag a form
        // error. Sylius won't flush an invalid form, so the change is rolled back.
        if (!$admin->isPasswordLoginAllowed() && !$this->guard->canDisablePasswordLoginForAdminUser($admin)) {
            $form->get('passwordLoginAllowed')->addError(new FormError(
                $this->translator->trans('three_brs.password_login_control.cannot_disable_last_method', [], 'validators'),
            ));
        }
    }

    public static function getExtendedTypes(): iterable
    {
        yield AdminUserType::class;
    }
}
