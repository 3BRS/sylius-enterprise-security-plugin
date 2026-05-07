<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class PasswordPolicySettingsType extends AbstractType implements PasswordPolicySettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('min_length', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.min_length',
                'required' => true,
                'constraints' => [new Positive()],
            ])
            ->add('max_length', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.max_length',
                'required' => false,
                'constraints' => [new Positive()],
            ])
            ->add('require_uppercase', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.require_uppercase',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('require_lowercase', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.require_lowercase',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('require_numbers', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.require_numbers',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('require_special_characters', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.password_policy.require_special_characters',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_password_policy_settings';
    }
}
