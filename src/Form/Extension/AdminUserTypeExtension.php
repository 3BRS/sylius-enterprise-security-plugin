<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\AdminUserType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;

class AdminUserTypeExtension extends AbstractTypeExtension implements AdminUserTypeExtensionInterface
{
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
    }

    public static function getExtendedTypes(): iterable
    {
        yield AdminUserType::class;
    }
}
