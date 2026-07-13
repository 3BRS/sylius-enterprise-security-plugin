<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\AdminUserType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

class AdminUserTypeExtension extends AbstractTypeExtension implements AdminUserTypeExtensionInterface
{
    public function __construct(
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // When password login is disabled for the admin scope nobody signs in with a
        // password, so the password field must not be set or edited for anyone. This holds
        // regardless of the password-expiration trait, so it runs before the interface gate
        // below — otherwise an AdminUser without that trait would keep a working password field.
        if (!$this->passwordLoginChecker->isEnabled(SettingsScope::ADMIN)) {
            if ($builder->has('plainPassword')) {
                $builder->remove('plainPassword');
            }

            return;
        }

        $dataClass = $options['data_class'] ?? null;
        if ($dataClass !== null && is_a($dataClass, PasswordExpirationAdminUserInterface::class, true)) {
            $builder->add('forcePasswordChange', CheckboxType::class, [
                'label' => 'three_brs.form.admin_user.force_password_change',
                'required' => false,
            ]);
        }
    }

    public static function getExtendedTypes(): iterable
    {
        yield AdminUserType::class;
    }
}
