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
        $dataClass = $options['data_class'] ?? null;
        if ($dataClass === null || !is_a($dataClass, PasswordExpirationAdminUserInterface::class, true)) {
            return;
        }

        // When password login is disabled for the admin scope nobody signs in with a
        // password: drop the password field (it must not be set or edited for anyone) and
        // skip the force-password-change checkbox, which then makes no sense.
        if (!$this->passwordLoginChecker->isEnabled(SettingsScope::ADMIN)) {
            $builder->remove('plainPassword');

            return;
        }

        $builder->add('forcePasswordChange', CheckboxType::class, [
            'label' => 'three_brs.form.admin_user.force_password_change',
            'required' => false,
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        yield AdminUserType::class;
    }
}
