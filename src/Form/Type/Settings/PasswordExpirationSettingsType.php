<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SecuritySettingsBounds;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class PasswordExpirationSettingsType extends AbstractType implements PasswordExpirationSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.password_expiration.enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('days', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.password_expiration.days',
                'required' => true,
                'attr' => ['min' => 1, 'max' => SecuritySettingsBounds::PASSWORD_EXPIRATION_DAYS_MAX],
                'constraints' => [new NotBlank(), new Range(min: 1, max: SecuritySettingsBounds::PASSWORD_EXPIRATION_DAYS_MAX)],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_password_expiration_settings';
    }
}
