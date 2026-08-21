<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SecuritySettingsBounds;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class RateLimitSettingsType extends AbstractType implements RateLimitSettingsTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $actions */
        $actions = $options['actions'];

        foreach ($actions as $action) {
            $builder
                ->add($action . '_enabled', CheckboxType::class, [
                    'label' => 'three_brs.ui.security_settings.rate_limit.' . $action . '.enabled',
                    'required' => false,
                    'label_attr' => ['class' => 'checkbox-switch'],
                ])
                ->add($action . '_limit', IntegerType::class, [
                    'label' => 'three_brs.ui.security_settings.rate_limit.' . $action . '.limit',
                    'required' => true,
                    'attr' => ['min' => 1, 'max' => SecuritySettingsBounds::RATE_LIMIT_LIMIT_MAX],
                    'constraints' => [new NotBlank(), new Range(min: 1, max: SecuritySettingsBounds::RATE_LIMIT_LIMIT_MAX)],
                ])
                ->add($action . '_interval', ChoiceType::class, [
                    'label' => 'three_brs.ui.security_settings.rate_limit.' . $action . '.interval',
                    'choices' => [
                        'three_brs.ui.security_settings.rate_limit.interval_options.1_minute' => '1 minute',
                        'three_brs.ui.security_settings.rate_limit.interval_options.5_minutes' => '5 minutes',
                        'three_brs.ui.security_settings.rate_limit.interval_options.10_minutes' => '10 minutes',
                        'three_brs.ui.security_settings.rate_limit.interval_options.15_minutes' => '15 minutes',
                        'three_brs.ui.security_settings.rate_limit.interval_options.30_minutes' => '30 minutes',
                        'three_brs.ui.security_settings.rate_limit.interval_options.1_hour' => '1 hour',
                        'three_brs.ui.security_settings.rate_limit.interval_options.2_hours' => '2 hours',
                        'three_brs.ui.security_settings.rate_limit.interval_options.5_hours' => '5 hours',
                    ],
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'actions' => ['login', 'password_reset', 'register', 'magic_link'],
        ]);
        $resolver->setAllowedTypes('actions', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'three_brs_rate_limit_settings';
    }
}
