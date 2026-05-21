<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Positive;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorMode;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class TwoFactorSettingsType extends AbstractType implements TwoFactorSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $modeChoices = [];
        foreach (TwoFactorMode::cases() as $mode) {
            $modeChoices['three_brs.ui.security_settings.two_factor.mode_' . $mode->value] = $mode->value;
        }

        $builder
            ->add('recovery_codes_enabled', CheckboxType::class, [
                'label' => 'three_brs.ui.security_settings.two_factor.recovery_codes_enabled',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
            ])
            ->add('mode', ChoiceType::class, [
                'label' => 'three_brs.ui.security_settings.two_factor.mode',
                'choices' => $modeChoices,
                'expanded' => true,
                'required' => true,
            ])
            ->add('recovery_codes_count', IntegerType::class, [
                'label' => 'three_brs.ui.security_settings.two_factor.recovery_codes_count',
                'required' => true,
                'constraints' => [new Positive()],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_two_factor_settings';
    }
}
