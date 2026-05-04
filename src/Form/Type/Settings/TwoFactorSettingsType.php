<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class TwoFactorSettingsType extends AbstractType implements TwoFactorSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mode', ChoiceType::class, [
                'label' => 'three_brs.ui.security_settings.two_factor.mode',
                'choices' => [
                    'three_brs.ui.security_settings.two_factor.mode_disabled' => 'disabled',
                    'three_brs.ui.security_settings.two_factor.mode_optional' => 'optional',
                    'three_brs.ui.security_settings.two_factor.mode_enforced' => 'enforced',
                ],
                'expanded' => true,
                'required' => true,
            ])
            ->add('recovery_codes_count', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.two_factor.recovery_codes_count',
                'required' => true,
                'constraints' => [new Positive()],
            ])
            ->add('recovery_codes_enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.two_factor.recovery_codes_enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_two_factor_settings';
    }
}
